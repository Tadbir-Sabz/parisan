<?php
/**
 * Internal, concurrency-safe booking creation service.
 *
 * This file deliberately exposes no public REST/AJAX endpoint. Callers must
 * provide their own authorization and abuse/rate-limit controls.
 *
 * @package ParisanCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Acquire the shared per-staff advisory lock used by booking and calendar writes.
 *
 * @param int $staff_id Specialist post ID.
 * @return string|WP_Error Lock name or safe error.
 */
function parisan_core_acquire_staff_schedule_lock( $staff_id ) {
	global $wpdb;

	$staff_id = absint( $staff_id );
	if ( ! $staff_id ) {
		return new WP_Error( 'parisan_invalid_staff_lock', __( 'متخصص معتبر نیست.', 'parisan-core' ) );
	}
	$lock_name = 'parisan:' . substr( hash( 'sha256', $wpdb->dbname . '|' . $wpdb->prefix . '|staff|' . $staff_id ), 0, 48 );
	$acquired = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock_name ) );
	if ( '1' !== (string) $acquired ) {
		return new WP_Error( 'parisan_staff_lock_unavailable', __( 'تقویم متخصص در حال تغییر است؛ لطفاً دوباره تلاش کنید.', 'parisan-core' ) );
	}
	return $lock_name;
}

/**
 * Release a previously acquired staff schedule advisory lock.
 *
 * @param string $lock_name Exact lock name returned by acquire helper.
 * @return void
 */
function parisan_core_release_staff_schedule_lock( $lock_name ) {
	global $wpdb;

	if ( is_string( $lock_name ) && '' !== $lock_name ) {
		parisan_core_release_staff_schedule_lock( $lock_name );
	}
}

/**
 * Create a pending booking after revalidating availability under a staff lock.
 *
 * Required input keys: staff_id, service_id, starts_at_local (Y-m-d H:i),
 * customer_name, customer_phone, idempotency_token.
 * Optional: customer_email.
 *
 * The client token is never stored. Only its SHA-256 digest is persisted.
 * Service prices are entered in toman by the current admin UI; this service
 * snapshots the equivalent integer amount in IRR (1 toman = 10 IRR).
 *
 * @param array $input Untrusted booking request.
 * @return array|WP_Error Booking identity or a safe error.
 */
function parisan_core_create_booking( $input ) {
	global $wpdb;

	if ( ! is_array( $input ) ) {
		return new WP_Error( 'parisan_invalid_booking_input', __( 'اطلاعات رزرو معتبر نیست.', 'parisan-core' ) );
	}

	$staff_id   = isset( $input['staff_id'] ) ? absint( $input['staff_id'] ) : 0;
	$service_id = isset( $input['service_id'] ) ? absint( $input['service_id'] ) : 0;
	$local_start = isset( $input['starts_at_local'] ) && is_string( $input['starts_at_local'] ) ? trim( $input['starts_at_local'] ) : '';
	$name       = isset( $input['customer_name'] ) && is_string( $input['customer_name'] ) ? sanitize_text_field( $input['customer_name'] ) : '';
	$phone      = isset( $input['customer_phone'] ) && is_string( $input['customer_phone'] ) ? trim( $input['customer_phone'] ) : '';
	$raw_email  = isset( $input['customer_email'] ) && is_string( $input['customer_email'] ) ? trim( $input['customer_email'] ) : '';
	$email      = '' !== $raw_email ? sanitize_email( $raw_email ) : '';
	$token      = isset( $input['idempotency_token'] ) && is_string( $input['idempotency_token'] ) ? trim( $input['idempotency_token'] ) : '';
	$actor_id   = get_current_user_id();

	if ( ! $staff_id || ! $service_id || ! preg_match( '/^\d{4}-\d{2}-\d{2} (?:[01]\d|2[0-3]):[0-5]\d$/D', $local_start ) || '' === $name || strlen( $name ) > 190 || ! preg_match( '/^\+?[0-9 ()-]{7,24}$/D', $phone ) || ! preg_match( '/^[A-Za-z0-9._~-]{16,128}$/D', $token ) || ( '' !== $raw_email && ( '' === $email || ! is_email( $email ) ) ) ) {
		return new WP_Error( 'parisan_invalid_booking_input', __( 'اطلاعات مشتری یا زمان رزرو معتبر نیست.', 'parisan-core' ) );
	}
	$phone = preg_replace( '/[^+0-9]/', '', $phone );
	if ( strlen( $phone ) < 7 || strlen( $phone ) > 20 ) {
		return new WP_Error( 'parisan_invalid_customer_phone', __( 'شماره تماس معتبر نیست.', 'parisan-core' ) );
	}

	if ( 'publish' !== get_post_status( $staff_id ) || 'parisan_staff' !== get_post_type( $staff_id ) || 'publish' !== get_post_status( $service_id ) || 'parisan_service' !== get_post_type( $service_id ) ) {
		return new WP_Error( 'parisan_invalid_booking_target', __( 'خدمت یا متخصص انتخاب‌شده معتبر نیست.', 'parisan-core' ) );
	}
	$eligible = get_post_meta( $staff_id, '_parisan_staff_services', true );
	$eligible = is_array( $eligible ) ? array_map( 'absint', $eligible ) : array();
	if ( ! in_array( $service_id, $eligible, true ) ) {
		return new WP_Error( 'parisan_service_not_available_for_staff', __( 'این خدمت توسط متخصص انتخاب‌شده ارائه نمی‌شود.', 'parisan-core' ) );
	}

	$duration = absint( get_post_meta( $service_id, '_parisan_duration', true ) );
	$price_toman = absint( get_post_meta( $service_id, '_parisan_price', true ) );
	$price_type = get_post_meta( $service_id, '_parisan_price_type', true );
	if ( $duration < 1 || $duration > 1440 || $price_toman > intdiv( PHP_INT_MAX, 10 ) ) {
		return new WP_Error( 'parisan_invalid_service_configuration', __( 'تنظیمات مدت یا قیمت خدمت معتبر نیست.', 'parisan-core' ) );
	}
	if ( 'from' === $price_type ) {
		return new WP_Error( 'parisan_service_requires_price_confirmation', __( 'برای رزرو این خدمت ابتدا باید قیمت نهایی توسط سالن مشخص شود.', 'parisan-core' ) );
	}
	$price_irr = $price_toman * 10;

	$timezone = wp_timezone();
	$local_parts = explode( ' ', $local_start );
	$start_utc = parisan_core_local_datetime_to_utc( $local_parts[0], $local_parts[1], $timezone );
	if ( false === $start_utc ) {
		return new WP_Error( 'parisan_invalid_local_booking_time', __( 'زمان محلی انتخاب‌شده معتبر نیست.', 'parisan-core' ) );
	}
	$end_utc = $start_utc->modify( '+' . $duration . ' minutes' );
	$starts_sql = $start_utc->format( 'Y-m-d H:i:s' );
	$ends_sql = $end_utc->format( 'Y-m-d H:i:s' );
	$timezone_name = $timezone->getName();
	$idempotency_key = hash( 'sha256', $token );

	$bookings_table = $wpdb->prefix . 'parisan_bookings';
	$events_table = $wpdb->prefix . 'parisan_booking_events';
	foreach ( array( $bookings_table, $events_table ) as $table ) {
		$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->esc_like( $table ) ), ARRAY_A );
		if ( ! is_array( $status ) || empty( $status['Engine'] ) || 'innodb' !== strtolower( $status['Engine'] ) ) {
			return new WP_Error( 'parisan_transaction_storage_unavailable', __( 'ذخیره‌ساز تراکنشی امن برای رزرو آماده نیست.', 'parisan-core' ) );
		}
	}

	$lock_name = parisan_core_acquire_staff_schedule_lock( $staff_id );
	if ( is_wp_error( $lock_name ) ) {
		return new WP_Error( 'parisan_booking_lock_unavailable', __( 'سامانه در حال پردازش رزرو دیگری است؛ لطفاً دوباره تلاش کنید.', 'parisan-core' ) );
	}

	$transaction_started = false;
	try {
		$transaction_result = $wpdb->query( 'START TRANSACTION' );
		if ( false !== $transaction_result ) {
			$transaction_started = true;
		} else {
			return new WP_Error( 'parisan_booking_transaction_failed', __( 'ثبت رزرو در حال حاضر ممکن نیست.', 'parisan-core' ) );
		}

		$existing = $wpdb->get_row(
			$wpdb->prepare( "SELECT id, public_id, service_id, staff_id, customer_name, customer_phone, customer_email, starts_at_utc, ends_at_utc FROM {$bookings_table} WHERE idempotency_key = %s LIMIT 1", $idempotency_key ),
			ARRAY_A
		);
		if ( is_array( $existing ) ) {
			$same_payload = (int) $existing['service_id'] === $service_id
				&& (int) $existing['staff_id'] === $staff_id
				&& hash_equals( (string) $existing['customer_name'], $name )
				&& hash_equals( (string) $existing['customer_phone'], $phone )
				&& hash_equals( (string) $existing['customer_email'], $email )
				&& hash_equals( (string) $existing['starts_at_utc'], $starts_sql )
				&& hash_equals( (string) $existing['ends_at_utc'], $ends_sql );
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				$wpdb->query( 'ROLLBACK' );
				$transaction_started = false;
				return new WP_Error( 'parisan_booking_commit_failed', __( 'تأیید نهایی رزرو انجام نشد.', 'parisan-core' ) );
			}
			$transaction_started = false;
			if ( ! $same_payload ) {
				return new WP_Error( 'parisan_idempotency_conflict', __( 'کلید ثبت تکراری قبلاً برای اطلاعات دیگری استفاده شده است.', 'parisan-core' ) );
			}
			return array( 'booking_id' => (int) $existing['id'], 'public_id' => $existing['public_id'], 'reused' => true );
		}

		$slots = parisan_core_get_available_slots( $staff_id, $service_id, $local_parts[0] );
		if ( is_wp_error( $slots ) ) {
			$wpdb->query( 'ROLLBACK' );
			$transaction_started = false;
			return new WP_Error( 'parisan_booking_availability_failed', __( 'امکان بررسی ظرفیت رزرو وجود ندارد.', 'parisan-core' ) );
		}
		$slot_available = false;
		foreach ( $slots as $slot ) {
			if ( isset( $slot['starts_at_local'] ) && hash_equals( $slot['starts_at_local'], $local_start ) ) {
				$slot_available = true;
				break;
			}
		}
		if ( ! $slot_available ) {
			$wpdb->query( 'ROLLBACK' );
			$transaction_started = false;
			return new WP_Error( 'parisan_booking_slot_unavailable', __( 'این زمان دیگر در دسترس نیست.', 'parisan-core' ) );
		}

		$now = current_time( 'mysql', true );
		$public_id = wp_generate_uuid4();
		$inserted = $wpdb->insert(
			$bookings_table,
			array(
				'public_id' => $public_id,
				'service_id' => $service_id,
				'staff_id' => $staff_id,
				'customer_name' => $name,
				'customer_phone' => $phone,
				'customer_email' => $email,
				'starts_at_utc' => $starts_sql,
				'ends_at_utc' => $ends_sql,
				'salon_timezone' => $timezone_name,
				'status' => 'pending',
				'price_amount' => $price_irr,
				'currency_code' => 'IRR',
				'idempotency_key' => $idempotency_key,
				'created_at_utc' => $now,
				'updated_at_utc' => $now,
			),
			array( '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);
		if ( false === $inserted ) {
			$wpdb->query( 'ROLLBACK' );
			$transaction_started = false;
			$duplicate = $wpdb->get_row( $wpdb->prepare( "SELECT id, public_id, service_id, staff_id, customer_name, customer_phone, customer_email, starts_at_utc, ends_at_utc FROM {$bookings_table} WHERE idempotency_key = %s LIMIT 1", $idempotency_key ), ARRAY_A );
			if ( is_array( $duplicate ) ) {
				$same = (int) $duplicate['service_id'] === $service_id && (int) $duplicate['staff_id'] === $staff_id && hash_equals( (string) $duplicate['customer_name'], $name ) && hash_equals( (string) $duplicate['customer_phone'], $phone ) && hash_equals( (string) $duplicate['customer_email'], $email ) && hash_equals( (string) $duplicate['starts_at_utc'], $starts_sql ) && hash_equals( (string) $duplicate['ends_at_utc'], $ends_sql );
				return $same ? array( 'booking_id' => (int) $duplicate['id'], 'public_id' => $duplicate['public_id'], 'reused' => true ) : new WP_Error( 'parisan_idempotency_conflict', __( 'کلید ثبت تکراری قبلاً برای اطلاعات دیگری استفاده شده است.', 'parisan-core' ) );
			}
			return new WP_Error( 'parisan_booking_insert_failed', __( 'ثبت رزرو انجام نشد.', 'parisan-core' ) );
		}

		$booking_id = (int) $wpdb->insert_id;
		$event_data = array(
			'booking_id' => $booking_id,
			'event_type' => 'created',
			'from_status' => '',
			'to_status' => 'pending',
			'event_note' => null,
			'created_at_utc' => $now,
		);
		if ( $actor_id ) {
			$event_data['actor_user_id'] = $actor_id;
		}
		$event_inserted = $wpdb->insert( $events_table, $event_data );
		if ( false === $event_inserted ) {
			$wpdb->query( 'ROLLBACK' );
			$transaction_started = false;
			return new WP_Error( 'parisan_booking_audit_failed', __( 'ثبت رویداد ممیزی رزرو انجام نشد؛ هیچ رزروی ذخیره نشد.', 'parisan-core' ) );
		}

		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			$transaction_started = false;
			return new WP_Error( 'parisan_booking_commit_failed', __( 'تأیید نهایی رزرو انجام نشد.', 'parisan-core' ) );
		}
		$transaction_started = false;
		return array( 'booking_id' => $booking_id, 'public_id' => $public_id, 'reused' => false );
	} finally {
		if ( $transaction_started ) {
			$wpdb->query( 'ROLLBACK' );
		}
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
	}
}
