<?php
/**
 * Internal booking state transitions.
 *
 * @package ParisanCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Transition a booking through the controlled internal lifecycle.
 *
 * Allowed transitions:
 * - pending -> confirmed | cancelled | expired
 * - confirmed -> cancelled | completed | no_show
 *
 * This service is intentionally not exposed through REST or AJAX. A logged-in
 * WordPress user must be able to edit the assigned specialist record.
 *
 * @param int    $booking_id Booking row ID.
 * @param string $to_status Requested target status.
 * @param string $note Optional internal audit note.
 * @return array|WP_Error Safe transition result.
 */
function parisan_core_transition_booking( $booking_id, $to_status, $note = '' ) {
	global $wpdb;

	$booking_id = absint( $booking_id );
	$to_status = is_string( $to_status ) ? sanitize_key( $to_status ) : '';
	$note = is_string( $note ) ? sanitize_textarea_field( $note ) : '';
	$note_length = function_exists( 'mb_strlen' ) ? mb_strlen( $note, 'UTF-8' ) : strlen( $note );
	if ( $note_length > 1000 ) {
		$note = function_exists( 'mb_substr' ) ? mb_substr( $note, 0, 1000, 'UTF-8' ) : substr( $note, 0, 1000 );
	}

	$transitions = array(
		'pending' => array( 'confirmed', 'cancelled', 'expired' ),
		'confirmed' => array( 'cancelled', 'completed', 'no_show' ),
	);
	if ( ! $booking_id || ! in_array( $to_status, array( 'confirmed', 'cancelled', 'expired', 'completed', 'no_show' ), true ) ) {
		return new WP_Error( 'parisan_invalid_booking_transition', __( 'درخواست تغییر وضعیت رزرو معتبر نیست.', 'parisan-core' ) );
	}

	$bookings_table = $wpdb->prefix . 'parisan_bookings';
	$events_table = $wpdb->prefix . 'parisan_booking_events';
	$exceptions_table = $wpdb->prefix . 'parisan_schedule_exceptions';
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
			return new WP_Error( 'parisan_booking_transaction_failed', __( 'تغییر وضعیت رزرو در حال حاضر ممکن نیست.', 'parisan-core' ) );
		}
		$transaction_started = true;
		$booking = $wpdb->get_row(
			$wpdb->prepare( "SELECT id, staff_id, starts_at_utc, ends_at_utc, status FROM {$bookings_table} WHERE id = %d FOR UPDATE", $booking_id ),
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
			return new WP_Error( 'parisan_booking_transition_forbidden', __( 'اجازه تغییر وضعیت این رزرو را ندارید.', 'parisan-core' ) );
		}

		$current_status = (string) $booking['status'];
		if ( $current_status === $to_status ) {
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				$wpdb->query( 'ROLLBACK' );
				$transaction_started = false;
				return new WP_Error( 'parisan_booking_commit_failed', __( 'تأیید تغییر وضعیت انجام نشد.', 'parisan-core' ) );
			}
			$transaction_started = false;
			return array( 'booking_id' => $booking_id, 'from_status' => $current_status, 'to_status' => $to_status, 'reused' => true );
		}
		if ( ! isset( $transitions[ $current_status ] ) || ! in_array( $to_status, $transitions[ $current_status ], true ) ) {
			$wpdb->query( 'ROLLBACK' );
			$transaction_started = false;
			return new WP_Error( 'parisan_invalid_booking_transition', __( 'این تغییر وضعیت در چرخه رزرو مجاز نیست.', 'parisan-core' ) );
		}

		if ( 'confirmed' === $to_status ) {
			$overlapping_bookings = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$bookings_table} WHERE staff_id = %d AND id <> %d AND status IN ('pending','confirmed') AND starts_at_utc < %s AND ends_at_utc > %s",
					$staff_id,
					$booking_id,
					$booking['ends_at_utc'],
					$booking['starts_at_utc']
				)
			);
			$overlapping_exceptions = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$exceptions_table} WHERE staff_id = %d AND exception_type = 'unavailable' AND starts_at_utc < %s AND ends_at_utc > %s",
					$staff_id,
					$booking['ends_at_utc'],
					$booking['starts_at_utc']
				)
			);
			if ( $overlapping_bookings > 0 || $overlapping_exceptions > 0 ) {
				$wpdb->query( 'ROLLBACK' );
				$transaction_started = false;
				return new WP_Error( 'parisan_booking_confirmation_conflict', __( 'به‌دلیل تداخل با رزرو یا عدم دسترسی ثبت‌شده، تأیید این نوبت ممکن نیست.', 'parisan-core' ) );
			}
		}

		$now = current_time( 'mysql', true );
		$updated = $wpdb->update(
			$bookings_table,
			array( 'status' => $to_status, 'updated_at_utc' => $now ),
			array( 'id' => $booking_id, 'status' => $current_status ),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);
		if ( false === $updated || 1 !== $updated ) {
			$wpdb->query( 'ROLLBACK' );
			$transaction_started = false;
			return new WP_Error( 'parisan_booking_transition_update_failed', __( 'ذخیره وضعیت جدید رزرو انجام نشد.', 'parisan-core' ) );
		}

		$event_data = array(
			'booking_id' => $booking_id,
			'actor_user_id' => $actor_id,
			'event_type' => 'status_changed',
			'from_status' => $current_status,
			'to_status' => $to_status,
			'event_note' => $note,
			'created_at_utc' => $now,
		);
		if ( false === $wpdb->insert( $events_table, $event_data ) ) {
			$wpdb->query( 'ROLLBACK' );
			$transaction_started = false;
			return new WP_Error( 'parisan_booking_audit_failed', __( 'ثبت رویداد ممیزی انجام نشد؛ وضعیت رزرو تغییر نکرد.', 'parisan-core' ) );
		}

		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			$transaction_started = false;
			return new WP_Error( 'parisan_booking_commit_failed', __( 'تأیید نهایی تغییر وضعیت انجام نشد.', 'parisan-core' ) );
		}
		$transaction_started = false;
		return array( 'booking_id' => $booking_id, 'from_status' => $current_status, 'to_status' => $to_status, 'reused' => false );
	} finally {
		if ( $transaction_started ) {
			$wpdb->query( 'ROLLBACK' );
		}
		parisan_core_release_staff_schedule_lock( $lock_name );
	}
}
