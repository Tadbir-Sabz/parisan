<?php
/**
 * Internal booking rescheduling service.
 *
 * @package ParisanCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Move an active booking to a new salon-local start time.
 *
 * The original booked duration and price snapshot are preserved. The current
 * booking is excluded from the advisory availability query, while every other
 * active booking and unavailable exception remains a conflict.
 *
 * @param int    $booking_id Booking row ID.
 * @param string $new_start_local New salon-local start (Y-m-d H:i).
 * @param string $note Optional internal audit note.
 * @return array|WP_Error Safe reschedule result.
 */
function parisan_core_reschedule_booking( $booking_id, $new_start_local, $note = '' ) {
	global $wpdb;

	$booking_id = absint( $booking_id );
	$new_start_local = is_string( $new_start_local ) ? trim( $new_start_local ) : '';
	$note = is_string( $note ) ? sanitize_textarea_field( $note ) : '';
	if ( ! $booking_id || ! preg_match( '/^\\d{4}-\\d{2}-\\d{2} (?:[01]\\d|2[0-3]):[0-5]\\d$/D', $new_start_local ) ) {
		return new WP_Error( 'parisan_invalid_reschedule_input', __( 'تاریخ یا ساعت جدید معتبر نیست.', 'parisan-core' ) );
	}

	$bookings_table = $wpdb->prefix . 'parisan_bookings';
	$events_table = $wpdb->prefix . 'parisan_booking_events';
	foreach ( array( $bookings_table, $events_table ) as $table ) {
		$table_status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->esc_like( $table ) ), ARRAY_A );
		if ( ! is_array( $table_status ) || empty( $table_status['Engine'] ) || 'innodb' !== strtolower( $table_status['Engine'] ) ) {
			return new WP_Error( 'parisan_transaction_storage_unavailable', __( 'ذخیره‌ساز تراکنشی امن برای رزرو آماده نیست.', 'parisan-core' ) );
		}
	}
	$staff_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT staff_id FROM {$bookings_table} WHERE id = %d", $booking_id ) );
	if ( ! $staff_id ) {
		return new WP_Error( 'parisan_booking_not_found', __( 'رزرو موردنظر پیدا نشد.', 'parisan-core' ) );
	}

	$lock_name = parisan_core_acquire_staff_schedule_lock( $staff_id );
	if ( is_wp_error( $lock_name ) ) {
		return new WP_Error( 'parisan_booking_lock_unavailable', __( 'رزرو در حال پردازش است؛ لطفاً دوباره تلاش کنید.', 'parisan-core' ) );
	}

	$transaction_started = false;
	try {
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new WP_Error( 'parisan_booking_transaction_failed', __( 'تغییر زمان رزرو در حال حاضر ممکن نیست.', 'parisan-core' ) );
		}
		$transaction_started = true;
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, service_id, staff_id, starts_at_utc, ends_at_utc, duration_minutes, salon_timezone, status
				FROM {$bookings_table} WHERE id = %d FOR UPDATE",
				$booking_id
			),
			ARRAY_A
		);
		if ( ! is_array( $booking ) ) {
			$wpdb->query( 'ROLLBACK' );
			$transaction_started = false;
			return new WP_Error( 'parisan_booking_not_found', __( 'رزرو موردنظر پیدا نشد.', 'parisan-core' ) );
		}

		$actor_id = get_current_user_id();
		if ( ! $actor_id || ! current_user_can( 'edit_post', (int) $booking['staff_id'] ) ) {
			$wpdb->query( 'ROLLBACK' );
			$transaction_started = false;
			return new WP_Error( 'parisan_booking_transition_forbidden', __( 'اجازه تغییر زمان این رزرو را ندارید.', 'parisan-core' ) );
		}
		if ( ! in_array( $booking['status'], array( 'pending', 'confirmed' ), true ) ) {
			$wpdb->query( 'ROLLBACK' );
			$transaction_started = false;
			return new WP_Error( 'parisan_booking_reschedule_not_allowed', __( 'زمان رزرو در این وضعیت قابل تغییر نیست.', 'parisan-core' ) );
		}

		$timezone = wp_timezone();
		$parts = explode( ' ', $new_start_local );
		$new_start_utc = parisan_core_local_datetime_to_utc( $parts[0], $parts[1], $timezone );
		if ( false === $new_start_utc ) {
			$wpdb->query( 'ROLLBACK' );
			$transaction_started = false;
			return new WP_Error( 'parisan_invalid_local_booking_time', __( 'زمان محلی انتخاب‌شده معتبر نیست.', 'parisan-core' ) );
		}
		if ( $new_start_utc <= new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) ) {
			$wpdb->query( 'ROLLBACK' );
			$transaction_started = false;
			return new WP_Error( 'parisan_booking_time_in_past', __( 'زمان رزرو باید در آینده باشد.', 'parisan-core' ) );
		}

		$new_start_sql = $new_start_utc->format( 'Y-m-d H:i:s' );
		if ( hash_equals( (string) $booking['starts_at_utc'], $new_start_sql ) ) {
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				$wpdb->query( 'ROLLBACK' );
				$transaction_started = false;
				return new WP_Error( 'parisan_booking_commit_failed', __( 'تأیید تغییر زمان انجام نشد.', 'parisan-core' ) );
			}
			$transaction_started = false;
			return array( 'booking_id' => $booking_id, 'starts_at_utc' => $new_start_sql, 'reused' => true );
		}

		$duration = absint( $booking['duration_minutes'] );
		if ( $duration < 1 ) {
			$old_start = new DateTimeImmutable( $booking['starts_at_utc'], new DateTimeZone( 'UTC' ) );
			$old_end = new DateTimeImmutable( $booking['ends_at_utc'], new DateTimeZone( 'UTC' ) );
			$duration_seconds = $old_end->getTimestamp() - $old_start->getTimestamp();
			if ( $duration_seconds < 60 || 0 !== $duration_seconds % 60 ) {
				$wpdb->query( 'ROLLBACK' );
				$transaction_started = false;
				return new WP_Error( 'parisan_booking_duration_invalid', __( 'مدت زمان ثبت‌شده برای این رزرو معتبر نیست.', 'parisan-core' ) );
			}
			$duration = intdiv( $duration_seconds, 60 );
		}
		if ( $duration < 1 || $duration > 1440 ) {
			$wpdb->query( 'ROLLBACK' );
			$transaction_started = false;
			return new WP_Error( 'parisan_booking_duration_invalid', __( 'مدت زمان ثبت‌شده برای این رزرو معتبر نیست.', 'parisan-core' ) );
		}

		$local_date = $parts[0];
		$slots = parisan_core_get_available_slots( (int) $booking['staff_id'], (int) $booking['service_id'], $local_date, $booking_id, $duration );
		if ( is_wp_error( $slots ) ) {
			$wpdb->query( 'ROLLBACK' );
			$transaction_started = false;
			return new WP_Error( 'parisan_booking_availability_failed', __( 'امکان بررسی ظرفیت زمان جدید وجود ندارد.', 'parisan-core' ) );
		}
		$slot_available = false;
		foreach ( $slots as $slot ) {
			if ( isset( $slot['starts_at_local'] ) && hash_equals( $slot['starts_at_local'], $new_start_local ) ) {
				$slot_available = true;
				break;
			}
		}
		if ( ! $slot_available ) {
			$wpdb->query( 'ROLLBACK' );
			$transaction_started = false;
			return new WP_Error( 'parisan_booking_slot_unavailable', __( 'زمان جدید با برنامه متخصص، نوبت دیگر یا عدم دسترسی ثبت‌شده تداخل دارد.', 'parisan-core' ) );
		}

		$new_end_utc = $new_start_utc->modify( '+' . $duration . ' minutes' );
		$new_end_sql = $new_end_utc->format( 'Y-m-d H:i:s' );
		$now = current_time( 'mysql', true );
		$updated = $wpdb->update(
			$bookings_table,
			array(
				'starts_at_utc' => $new_start_sql,
				'ends_at_utc' => $new_end_sql,
				'duration_minutes' => $duration,
				'salon_timezone' => $timezone->getName(),
				'updated_at_utc' => $now,
			),
			array( 'id' => $booking_id, 'status' => $booking['status'] ),
			array( '%s', '%s', '%d', '%s', '%s' ),
			array( '%d', '%s' )
		);
		if ( false === $updated || 1 !== $updated ) {
			$wpdb->query( 'ROLLBACK' );
			$transaction_started = false;
			return new WP_Error( 'parisan_booking_reschedule_update_failed', __( 'ذخیره زمان جدید انجام نشد.', 'parisan-core' ) );
		}

		$event_note = 'Rescheduled from ' . $booking['starts_at_utc'] . ' UTC to ' . $new_start_sql . ' UTC';
		if ( '' !== $note ) {
			$event_note .= ' — ' . $note;
		}
		$event_inserted = $wpdb->insert(
			$events_table,
			array(
				'booking_id' => $booking_id,
				'actor_user_id' => $actor_id,
				'event_type' => 'rescheduled',
				'from_status' => $booking['status'],
				'to_status' => $booking['status'],
				'event_note' => $event_note,
				'created_at_utc' => $now,
			)
		);
		if ( false === $event_inserted ) {
			$wpdb->query( 'ROLLBACK' );
			$transaction_started = false;
			return new WP_Error( 'parisan_booking_audit_failed', __( 'ثبت رویداد تغییر زمان انجام نشد؛ زمان رزرو تغییر نکرد.', 'parisan-core' ) );
		}

		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			$transaction_started = false;
			return new WP_Error( 'parisan_booking_commit_failed', __( 'تأیید نهایی تغییر زمان انجام نشد.', 'parisan-core' ) );
		}
		$transaction_started = false;
		return array( 'booking_id' => $booking_id, 'starts_at_utc' => $new_start_sql, 'ends_at_utc' => $new_end_sql, 'duration_minutes' => $duration, 'reused' => false );
	} finally {
		if ( $transaction_started ) {
			$wpdb->query( 'ROLLBACK' );
		}
		parisan_core_release_staff_schedule_lock( $lock_name );
	}
}
