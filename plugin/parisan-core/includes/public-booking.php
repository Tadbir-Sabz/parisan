<?php
/**
 * Explicitly gated public booking API and scoped customer access tokens.
 *
 * Public routes are registered only when PARISAN_PUBLIC_BOOKING_ENABLED is
 * explicitly set to true in wp-config.php. The default is disabled.
 *
 * @package ParisanCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the site owner has explicitly enabled the public booking API.
 *
 * @return bool
 */
function parisan_core_public_booking_enabled() {
	return defined( 'PARISAN_PUBLIC_BOOKING_ENABLED' ) && true === PARISAN_PUBLIC_BOOKING_ENABLED;
}

/**
 * Hash a customer access token with the site's authentication salt.
 *
 * @param string $token Raw high-entropy token.
 * @return string
 */
function parisan_core_customer_token_hash( $token ) {
	return hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
}

/**
 * Store/rotate a customer token inside the caller's booking transaction.
 *
 * Only a keyed digest is persisted. Existing tokens for this booking are
 * revoked atomically before the replacement token is inserted.
 *
 * @param int    $booking_id Booking row ID.
 * @param string $token Raw 256-bit token encoded as lowercase hex.
 * @return bool
 */
function parisan_core_store_customer_access_token( $booking_id, $token ) {
	global $wpdb;

	$booking_id = absint( $booking_id );
	if ( ! $booking_id || ! is_string( $token ) || ! preg_match( '/^[a-f0-9]{64}$/D', $token ) ) {
		return false;
	}

	$bookings_table = $wpdb->prefix . 'parisan_bookings';
	$tokens_table = $wpdb->prefix . 'parisan_customer_tokens';
	$ends_at = $wpdb->get_var( $wpdb->prepare( "SELECT ends_at_utc FROM {$bookings_table} WHERE id = %d", $booking_id ) );
	if ( ! is_string( $ends_at ) || '' === $ends_at ) {
		return false;
	}

	try {
		$expiry = new DateTimeImmutable( $ends_at, new DateTimeZone( 'UTC' ) );
		$expiry = $expiry->modify( '+30 days' );
	} catch ( Exception $exception ) {
		return false;
	}

	$now = current_time( 'mysql', true );
	$revoked = $wpdb->update(
		$tokens_table,
		array( 'revoked_at_utc' => $now ),
		array( 'booking_id' => $booking_id, 'revoked_at_utc' => null ),
		array( '%s' ),
		array( '%d', null )
	);
	if ( false === $revoked ) {
		return false;
	}

	$inserted = $wpdb->insert(
		$tokens_table,
		array(
			'booking_id' => $booking_id,
			'token_hash' => parisan_core_customer_token_hash( $token ),
			'expires_at_utc' => $expiry->format( 'Y-m-d H:i:s' ),
			'revoked_at_utc' => null,
			'created_at_utc' => $now,
			'last_used_at_utc' => null,
		),
		array( '%d', '%s', '%s', null, '%s', null )
	);

	return false !== $inserted;
}

/**
 * Resolve a token without ever returning the stored digest.
 *
 * @param string $token Raw token from the dedicated request header.
 * @return array|false
 */
function parisan_core_resolve_customer_access_token( $token ) {
	global $wpdb;

	if ( ! is_string( $token ) || ! preg_match( '/^[a-f0-9]{64}$/D', $token ) ) {
		return false;
	}

	$tokens_table = $wpdb->prefix . 'parisan_customer_tokens';
	$bookings_table = $wpdb->prefix . 'parisan_bookings';
	$now = current_time( 'mysql', true );
	$token_row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, booking_id FROM {$tokens_table} WHERE token_hash = %s AND revoked_at_utc IS NULL AND expires_at_utc > %s LIMIT 1",
			parisan_core_customer_token_hash( $token ),
			$now
		),
		ARRAY_A
	);
	if ( ! is_array( $token_row ) ) {
		return false;
	}

	$booking = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, public_id, service_id, staff_id, starts_at_utc, ends_at_utc, duration_minutes, salon_timezone, status, price_amount, currency_code FROM {$bookings_table} WHERE id = %d LIMIT 1",
			(int) $token_row['booking_id']
		),
		ARRAY_A
	);
	if ( ! is_array( $booking ) ) {
		return false;
	}

	$wpdb->update(
		$tokens_table,
		array( 'last_used_at_utc' => $now ),
		array( 'id' => (int) $token_row['id'] ),
		array( '%s' ),
		array( '%d' )
	);

	return $booking;
}

/**
 * Apply no-store headers to all public booking responses.
 *
 * @param WP_REST_Response $response Response.
 * @return WP_REST_Response
 */
function parisan_core_private_booking_response( $response ) {
	$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
	$response->header( 'Pragma', 'no-cache' );
	$response->header( 'Vary', 'Origin, Cookie, Authorization, X-Parisan-Booking-Token' );
	$response->header( 'X-Content-Type-Options', 'nosniff' );
	return $response;
}

/**
 * Build a generic public API error response.
 *
 * @param string $message Safe user-facing message.
 * @param int    $status HTTP status.
 * @return WP_REST_Response
 */
function parisan_core_public_booking_error( $message, $status = 400 ) {
	return parisan_core_private_booking_response( new WP_REST_Response( array( 'message' => $message ), $status ) );
}

/**
 * Enforce same-origin requests for anonymous state-changing browser actions.
 *
 * @param WP_REST_Request $request Request.
 * @return bool
 */
function parisan_core_public_booking_same_origin( $request ) {
	$origin = $request->get_header( 'origin' );
	if ( ! is_string( $origin ) || '' === $origin ) {
		return false;
	}

	$origin_parts = wp_parse_url( $origin );
	$home_parts = wp_parse_url( home_url( '/' ) );
	if ( ! is_array( $origin_parts ) || ! is_array( $home_parts ) || empty( $origin_parts['scheme'] ) || empty( $origin_parts['host'] ) || empty( $home_parts['scheme'] ) || empty( $home_parts['host'] ) ) {
		return false;
	}

	$origin_scheme = strtolower( $origin_parts['scheme'] );
	$home_scheme = strtolower( $home_parts['scheme'] );
	$origin_port = isset( $origin_parts['port'] ) ? (int) $origin_parts['port'] : ( 'https' === $origin_scheme ? 443 : 80 );
	$home_port = isset( $home_parts['port'] ) ? (int) $home_parts['port'] : ( 'https' === $home_scheme ? 443 : 80 );

	return $origin_scheme === $home_scheme
		&& strtolower( $origin_parts['host'] ) === strtolower( $home_parts['host'] )
		&& $origin_port === $home_port;
}

/**
 * Enforce the reusable request limiter and return a generic response on error.
 *
 * @param string $scope Stable endpoint scope.
 * @param int    $limit Request count.
 * @return true|WP_REST_Response
 */
function parisan_core_public_booking_rate_check( $scope, $limit ) {
	$result = parisan_core_check_rate_limit( $scope, $limit, 300 );
	if ( true === $result ) {
		return true;
	}
	if ( is_wp_error( $result ) && 'parisan_rate_limit_exceeded' === $result->get_error_code() ) {
		$response = parisan_core_public_booking_error( __( 'درخواست‌های زیادی ثبت شده است؛ کمی بعد دوباره تلاش کنید.', 'parisan-core' ), 429 );
		$details = $result->get_error_data();
		if ( is_array( $details ) && ! empty( $details['retry_after'] ) ) {
			$response->header( 'Retry-After', (string) absint( $details['retry_after'] ) );
		}
		return $response;
	}
	return parisan_core_public_booking_error( __( 'سرویس موقتاً در دسترس نیست؛ کمی بعد تلاش کنید.', 'parisan-core' ), 503 );
}

/**
 * Read the token only from its dedicated header, never from URL/query/body.
 *
 * @param WP_REST_Request $request Request.
 * @return string
 */
function parisan_core_public_booking_request_token( $request ) {
	$token = $request->get_header( 'x-parisan-booking-token' );
	return is_string( $token ) ? trim( $token ) : '';
}

/**
 * Public, read-only availability handler.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function parisan_core_rest_public_availability( $request ) {
	$limited = parisan_core_public_booking_rate_check( 'public_availability', 60 );
	if ( true !== $limited ) {
		return $limited;
	}

	$staff_id = absint( $request->get_param( 'staff_id' ) );
	$service_id = absint( $request->get_param( 'service_id' ) );
	$date = $request->get_param( 'date' );
	if ( ! is_string( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $date ) ) {
		return parisan_core_public_booking_error( __( 'تاریخ انتخاب‌شده معتبر نیست.', 'parisan-core' ), 400 );
	}

	$timezone = wp_timezone();
	$requested = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, $timezone );
	$errors = DateTimeImmutable::getLastErrors();
	$today = new DateTimeImmutable( 'today', $timezone );
	if ( false === $requested || ( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) || $requested->format( 'Y-m-d' ) !== $date || $requested < $today || $requested > $today->modify( '+90 days' ) ) {
		return parisan_core_public_booking_error( __( 'تاریخ باید در بازه ۹۰ روز آینده باشد.', 'parisan-core' ), 400 );
	}

	$slots = parisan_core_get_available_slots( $staff_id, $service_id, $date );
	if ( is_wp_error( $slots ) ) {
		return parisan_core_public_booking_error( __( 'در حال حاضر امکان بررسی زمان‌های آزاد وجود ندارد.', 'parisan-core' ), 400 );
	}

	$safe_slots = array();
	foreach ( array_slice( $slots, 0, 100 ) as $slot ) {
		if ( isset( $slot['starts_at_local'] ) && is_string( $slot['starts_at_local'] ) ) {
			$safe_slots[] = array( 'starts_at_local' => $slot['starts_at_local'] );
		}
	}

	return parisan_core_private_booking_response( new WP_REST_Response( array( 'date' => $date, 'slots' => $safe_slots ), 200 ) );
}

/**
 * Create a pending public booking and issue a one-time customer access token.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function parisan_core_rest_public_create_booking( $request ) {
	if ( ! parisan_core_public_booking_same_origin( $request ) ) {
		return parisan_core_public_booking_error( __( 'درخواست معتبر نیست.', 'parisan-core' ), 403 );
	}
	$limited = parisan_core_public_booking_rate_check( 'public_booking_create', 5 );
	if ( true !== $limited ) {
		return $limited;
	}

	$starts_at_local = $request->get_param( 'starts_at_local' );
	if ( ! is_string( $starts_at_local ) || ! preg_match( '/^\\d{4}-\\d{2}-\\d{2} (?:[01]\\d|2[0-3]):[0-5]\\d$/D', $starts_at_local ) ) {
		return parisan_core_public_booking_error( __( 'زمان انتخاب‌شده معتبر نیست.', 'parisan-core' ), 400 );
	}
	$booking_timezone = wp_timezone();
	$booking_date = substr( $starts_at_local, 0, 10 );
	$requested_date = DateTimeImmutable::createFromFormat( '!Y-m-d', $booking_date, $booking_timezone );
	$date_errors = DateTimeImmutable::getLastErrors();
	$today = new DateTimeImmutable( 'today', $booking_timezone );
	if ( false === $requested_date || ( is_array( $date_errors ) && ( $date_errors['warning_count'] || $date_errors['error_count'] ) ) || $requested_date->format( 'Y-m-d' ) !== $booking_date || $requested_date < $today || $requested_date > $today->modify( '+90 days' ) ) {
		return parisan_core_public_booking_error( __( 'تاریخ رزرو باید در بازه ۹۰ روز آینده باشد.', 'parisan-core' ), 400 );
	}

	$idempotency = $request->get_header( 'idempotency-key' );
	if ( ! is_string( $idempotency ) || ! preg_match( '/^[a-f0-9]{64}$/D', $idempotency ) ) {
		return parisan_core_public_booking_error( __( 'شناسه درخواست معتبر نیست؛ صفحه را تازه‌سازی و دوباره تلاش کنید.', 'parisan-core' ), 400 );
	}

	try {
		$customer_token = bin2hex( random_bytes( 32 ) );
	} catch ( Throwable $exception ) {
		return parisan_core_public_booking_error( __( 'ثبت رزرو موقتاً ممکن نیست.', 'parisan-core' ), 503 );
	}

	$input = array(
		'staff_id' => $request->get_param( 'staff_id' ),
		'service_id' => $request->get_param( 'service_id' ),
		'starts_at_local' => $request->get_param( 'starts_at_local' ),
		'customer_name' => $request->get_param( 'customer_name' ),
		'customer_phone' => $request->get_param( 'customer_phone' ),
		'customer_email' => $request->get_param( 'customer_email' ),
		'idempotency_token' => $idempotency,
		'customer_access_token' => $customer_token,
	);
	$result = parisan_core_create_booking( $input );
	if ( is_wp_error( $result ) ) {
		$code = $result->get_error_code();
		if ( in_array( $code, array( 'parisan_booking_slot_unavailable', 'parisan_idempotency_conflict', 'parisan_service_requires_price_confirmation' ), true ) ) {
			return parisan_core_public_booking_error( __( 'این درخواست قابل ثبت نیست؛ اطلاعات را بررسی و زمان دیگری انتخاب کنید.', 'parisan-core' ), 409 );
		}
		if ( in_array( $code, array( 'parisan_invalid_booking_input', 'parisan_invalid_booking_target', 'parisan_service_not_available_for_staff', 'parisan_invalid_local_booking_time', 'parisan_booking_time_in_past' ), true ) ) {
			return parisan_core_public_booking_error( __( 'اطلاعات رزرو معتبر نیست.', 'parisan-core' ), 400 );
		}
		return parisan_core_public_booking_error( __( 'ثبت رزرو در حال حاضر ممکن نیست؛ کمی بعد تلاش کنید.', 'parisan-core' ), 503 );
	}

	return parisan_core_private_booking_response(
		new WP_REST_Response(
			array(
				'status' => 'pending',
				'customer_access_token' => $customer_token,
				'message' => __( 'درخواست نوبت ثبت شد و پس از بررسی سالن اطلاع‌رسانی خواهد شد.', 'parisan-core' ),
			),
			201
		)
	);
}

/**
 * Resolve the authenticated customer booking or return a generic 404.
 *
 * @param WP_REST_Request $request Request.
 * @return array|WP_REST_Response
 */
function parisan_core_public_booking_from_request( $request ) {
	$limited = parisan_core_public_booking_rate_check( 'customer_booking_access', 30 );
	if ( true !== $limited ) {
		return $limited;
	}
	$booking = parisan_core_resolve_customer_access_token( parisan_core_public_booking_request_token( $request ) );
	if ( ! is_array( $booking ) ) {
		return parisan_core_public_booking_error( __( 'رزرو پیدا نشد یا دسترسی آن معتبر نیست.', 'parisan-core' ), 404 );
	}
	return $booking;
}

/**
 * Return a privacy-minimal customer booking view.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function parisan_core_rest_customer_booking( $request ) {
	$booking = parisan_core_public_booking_from_request( $request );
	if ( $booking instanceof WP_REST_Response ) {
		return $booking;
	}

	try {
		$utc = new DateTimeImmutable( $booking['starts_at_utc'], new DateTimeZone( 'UTC' ) );
		$local = $utc->setTimezone( new DateTimeZone( $booking['salon_timezone'] ) );
	} catch ( Exception $exception ) {
		return parisan_core_public_booking_error( __( 'اطلاعات رزرو در دسترس نیست.', 'parisan-core' ), 503 );
	}

	$data = array(
		'status' => $booking['status'],
		'service' => get_the_title( (int) $booking['service_id'] ),
		'specialist' => get_the_title( (int) $booking['staff_id'] ),
		'starts_at_local' => $local->format( DateTimeInterface::ATOM ),
		'starts_at_local_display' => $local->format( 'Y-m-d H:i' ),
		'timezone' => $booking['salon_timezone'],
		'price_amount' => (int) $booking['price_amount'],
		'currency_code' => $booking['currency_code'],
		'can_cancel' => in_array( $booking['status'], array( 'pending', 'confirmed' ), true ),
		'can_reschedule' => in_array( $booking['status'], array( 'pending', 'confirmed' ), true ),
	);
	return parisan_core_private_booking_response( new WP_REST_Response( $data, 200 ) );
}

/**
 * Return availability scoped to a token-owned booking and its existing duration.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function parisan_core_rest_customer_availability( $request ) {
	$booking = parisan_core_public_booking_from_request( $request );
	if ( $booking instanceof WP_REST_Response ) {
		return $booking;
	}
	if ( ! in_array( $booking['status'], array( 'pending', 'confirmed' ), true ) ) {
		return parisan_core_public_booking_error( __( 'این رزرو دیگر قابل تغییر زمان نیست.', 'parisan-core' ), 409 );
	}

	$date = $request->get_param( 'date' );
	if ( ! is_string( $date ) || ! preg_match( '/^\\d{4}-\\d{2}-\\d{2}$/D', $date ) ) {
		return parisan_core_public_booking_error( __( 'تاریخ انتخاب‌شده معتبر نیست.', 'parisan-core' ), 400 );
	}
	$timezone = wp_timezone();
	$requested = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, $timezone );
	$errors = DateTimeImmutable::getLastErrors();
	$today = new DateTimeImmutable( 'today', $timezone );
	if ( false === $requested || ( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) || $requested->format( 'Y-m-d' ) !== $date || $requested < $today || $requested > $today->modify( '+90 days' ) ) {
		return parisan_core_public_booking_error( __( 'تاریخ باید در بازه ۹۰ روز آینده باشد.', 'parisan-core' ), 400 );
	}

	$duration = absint( $booking['duration_minutes'] );
	$slots = parisan_core_get_available_slots(
		(int) $booking['staff_id'],
		(int) $booking['service_id'],
		$date,
		(int) $booking['id'],
		$duration > 0 ? $duration : null
	);
	if ( is_wp_error( $slots ) ) {
		return parisan_core_public_booking_error( __( 'در حال حاضر امکان بررسی زمان‌های آزاد وجود ندارد.', 'parisan-core' ), 503 );
	}

	$safe_slots = array();
	foreach ( array_slice( $slots, 0, 100 ) as $slot ) {
		if ( isset( $slot['starts_at_local'] ) && is_string( $slot['starts_at_local'] ) ) {
			$safe_slots[] = array( 'starts_at_local' => $slot['starts_at_local'] );
		}
	}
	return parisan_core_private_booking_response( new WP_REST_Response( array( 'date' => $date, 'slots' => $safe_slots ), 200 ) );
}

/**
 * Reschedule a booking using its scoped customer token.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function parisan_core_rest_customer_reschedule_booking( $request ) {
	if ( ! parisan_core_public_booking_same_origin( $request ) ) {
		return parisan_core_public_booking_error( __( 'درخواست معتبر نیست.', 'parisan-core' ), 403 );
	}
	$limited = parisan_core_public_booking_rate_check( 'customer_booking_reschedule', 5 );
	if ( true !== $limited ) {
		return $limited;
	}
	$booking = parisan_core_public_booking_from_request( $request );
	if ( $booking instanceof WP_REST_Response ) {
		return $booking;
	}
	if ( ! in_array( $booking['status'], array( 'pending', 'confirmed' ), true ) ) {
		return parisan_core_public_booking_error( __( 'این رزرو دیگر قابل تغییر زمان نیست.', 'parisan-core' ), 409 );
	}

	$new_start_local = $request->get_param( 'starts_at_local' );
	if ( ! is_string( $new_start_local ) || ! preg_match( '/^\\d{4}-\\d{2}-\\d{2} (?:[01]\\d|2[0-3]):[0-5]\\d$/D', $new_start_local ) ) {
		return parisan_core_public_booking_error( __( 'زمان انتخاب‌شده معتبر نیست.', 'parisan-core' ), 400 );
	}
	$timezone = wp_timezone();
	$date = substr( $new_start_local, 0, 10 );
	$requested = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, $timezone );
	$errors = DateTimeImmutable::getLastErrors();
	$today = new DateTimeImmutable( 'today', $timezone );
	if ( false === $requested || ( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) || $requested->format( 'Y-m-d' ) !== $date || $requested < $today || $requested > $today->modify( '+90 days' ) ) {
		return parisan_core_public_booking_error( __( 'تاریخ باید در بازه ۹۰ روز آینده باشد.', 'parisan-core' ), 400 );
	}

	$token = parisan_core_public_booking_request_token( $request );
	$result = parisan_core_reschedule_booking( (int) $booking['id'], $new_start_local, 'Customer self-service', $token );
	if ( is_wp_error( $result ) ) {
		$code = $result->get_error_code();
		if ( in_array( $code, array( 'parisan_booking_slot_unavailable', 'parisan_booking_reschedule_not_allowed' ), true ) ) {
			return parisan_core_public_booking_error( __( 'زمان انتخاب‌شده برای این رزرو قابل استفاده نیست.', 'parisan-core' ), 409 );
		}
		if ( in_array( $code, array( 'parisan_invalid_reschedule_input', 'parisan_invalid_local_booking_time', 'parisan_booking_time_in_past' ), true ) ) {
			return parisan_core_public_booking_error( __( 'زمان انتخاب‌شده معتبر نیست.', 'parisan-core' ), 400 );
		}
		if ( 'parisan_customer_booking_unavailable' === $code ) {
			return parisan_core_public_booking_error( __( 'رزرو پیدا نشد یا دسترسی آن معتبر نیست.', 'parisan-core' ), 404 );
		}
		return parisan_core_public_booking_error( __( 'تغییر زمان رزرو در حال حاضر ممکن نیست؛ کمی بعد تلاش کنید.', 'parisan-core' ), 503 );
	}

	try {
		$local = ( new DateTimeImmutable( $result['starts_at_utc'], new DateTimeZone( 'UTC' ) ) )->setTimezone( $timezone );
	} catch ( Exception $exception ) {
		return parisan_core_public_booking_error( __( 'اطلاعات زمان رزرو در دسترس نیست.', 'parisan-core' ), 503 );
	}
	return parisan_core_private_booking_response(
		new WP_REST_Response(
			array(
				'status' => 'rescheduled',
				'starts_at_local_display' => $local->format( 'Y-m-d H:i' ),
				'timezone' => $timezone->getName(),
			),
			200
		)
	);
}

/**
 * Cancel an appointment using only its scoped customer token.
 *
 * @param int $booking_id Booking ID.
 * @return true|WP_Error
 */
function parisan_core_cancel_booking_with_customer_token( $booking_id, $token ) {
	global $wpdb;

	$bookings_table = $wpdb->prefix . 'parisan_bookings';
	$events_table = $wpdb->prefix . 'parisan_booking_events';
	$tokens_table = $wpdb->prefix . 'parisan_customer_tokens';
	if ( ! is_string( $token ) || ! preg_match( '/^[a-f0-9]{64}$/D', $token ) ) {
		return new WP_Error( 'parisan_customer_booking_unavailable' );
	}
	$staff_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT staff_id FROM {$bookings_table} WHERE id = %d", absint( $booking_id ) ) );
	if ( ! $staff_id ) {
		return new WP_Error( 'parisan_customer_booking_unavailable' );
	}

	$lock_name = parisan_core_acquire_staff_schedule_lock( $staff_id );
	if ( is_wp_error( $lock_name ) ) {
		return new WP_Error( 'parisan_customer_booking_unavailable' );
	}

	$transaction_started = false;
	try {
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new WP_Error( 'parisan_customer_booking_unavailable' );
		}
		$transaction_started = true;
		$booking = $wpdb->get_row(
			$wpdb->prepare( "SELECT id, staff_id, status FROM {$bookings_table} WHERE id = %d FOR UPDATE", absint( $booking_id ) ),
			ARRAY_A
		);
		if ( ! is_array( $booking ) || ! in_array( $booking['status'], array( 'pending', 'confirmed' ), true ) ) {
			return new WP_Error( 'parisan_customer_booking_unavailable' );
		}

		$token_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$tokens_table} WHERE booking_id = %d AND token_hash = %s AND revoked_at_utc IS NULL AND expires_at_utc > %s LIMIT 1 FOR UPDATE",
				absint( $booking_id ),
				parisan_core_customer_token_hash( $token ),
				current_time( 'mysql', true )
			),
			ARRAY_A
		);
		if ( ! is_array( $token_row ) ) {
			return new WP_Error( 'parisan_customer_booking_unavailable' );
		}

		$now = current_time( 'mysql', true );
		$updated = $wpdb->update(
			$bookings_table,
			array( 'status' => 'cancelled', 'updated_at_utc' => $now ),
			array( 'id' => absint( $booking_id ), 'status' => $booking['status'] ),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);
		if ( 1 !== $updated ) {
			return new WP_Error( 'parisan_customer_booking_unavailable' );
		}

		$event = $wpdb->insert(
			$events_table,
			array(
				'booking_id' => absint( $booking_id ),
				'actor_user_id' => null,
				'event_type' => 'customer_cancelled',
				'from_status' => $booking['status'],
				'to_status' => 'cancelled',
				'event_note' => null,
				'created_at_utc' => $now,
			),
			array( '%d', null, '%s', '%s', '%s', null, '%s' )
		);
		if ( false === $event ) {
			return new WP_Error( 'parisan_customer_booking_unavailable' );
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			return new WP_Error( 'parisan_customer_booking_unavailable' );
		}
		$transaction_started = false;
		return true;
	} finally {
		if ( $transaction_started ) {
			$wpdb->query( 'ROLLBACK' );
		}
		parisan_core_release_staff_schedule_lock( $lock_name );
	}
}

/**
 * Cancel a booking after token ownership and same-origin checks.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function parisan_core_rest_customer_cancel_booking( $request ) {
	if ( ! parisan_core_public_booking_same_origin( $request ) ) {
		return parisan_core_public_booking_error( __( 'درخواست معتبر نیست.', 'parisan-core' ), 403 );
	}
	$booking = parisan_core_public_booking_from_request( $request );
	if ( $booking instanceof WP_REST_Response ) {
		return $booking;
	}
	$result = parisan_core_cancel_booking_with_customer_token( (int) $booking['id'], parisan_core_public_booking_request_token( $request ) );
	if ( is_wp_error( $result ) ) {
		return parisan_core_public_booking_error( __( 'این رزرو در حال حاضر قابل لغو نیست.', 'parisan-core' ), 409 );
	}
	return parisan_core_private_booking_response( new WP_REST_Response( array( 'status' => 'cancelled' ), 200 ) );
}

/**
 * Register routes only after explicit owner configuration.
 *
 * @return void
 */
function parisan_core_register_public_booking_routes() {
	if ( ! parisan_core_public_booking_enabled() ) {
		return;
	}

	register_rest_route(
		'parisan/v1',
		'/availability',
		array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => 'parisan_core_rest_public_availability',
			'permission_callback' => '__return_true',
		)
	);
	register_rest_route(
		'parisan/v1',
		'/bookings',
		array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => 'parisan_core_rest_public_create_booking',
			'permission_callback' => '__return_true',
		)
	);
	register_rest_route(
		'parisan/v1',
		'/customer-booking',
		array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => 'parisan_core_rest_customer_booking',
			'permission_callback' => '__return_true',
		)
	);
	register_rest_route(
		'parisan/v1',
		'/customer-booking/availability',
		array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => 'parisan_core_rest_customer_availability',
			'permission_callback' => '__return_true',
		)
	);
	register_rest_route(
		'parisan/v1',
		'/customer-booking/reschedule',
		array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => 'parisan_core_rest_customer_reschedule_booking',
			'permission_callback' => '__return_true',
		)
	);
	register_rest_route(
		'parisan/v1',
		'/customer-booking/cancel',
		array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => 'parisan_core_rest_customer_cancel_booking',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'parisan_core_register_public_booking_routes' );


/**
 * Render the Persian-first public booking wizard shortcode.
 *
 * The UI is deliberately unavailable while the public API feature flag is off.
 *
 * @return string
 */
function parisan_core_booking_shortcode() {
	if ( ! parisan_core_public_booking_enabled() ) {
		return '<section class="parisan-booking-shell" dir="rtl"><p class="parisan-booking-notice">' . esc_html__( 'رزرو آنلاین در حال حاضر فعال نیست.', 'parisan-core' ) . '</p></section>';
	}

	$services = get_posts(
		array(
			'post_type' => 'parisan_service',
			'post_status' => 'publish',
			'numberposts' => 200,
			'orderby' => 'title',
			'order' => 'ASC',
		)
	);
	$staff_members = get_posts(
		array(
			'post_type' => 'parisan_staff',
			'post_status' => 'publish',
			'numberposts' => 200,
			'orderby' => 'title',
			'order' => 'ASC',
		)
	);
	if ( ! $services || ! $staff_members ) {
		return '<section class="parisan-booking-shell" dir="rtl"><p class="parisan-booking-notice">' . esc_html__( 'برای فعال‌شدن رزرو آنلاین، ابتدا خدمات و متخصصان را در مدیریت تعریف کنید.', 'parisan-core' ) . '</p></section>';
	}

	$service_ids = array_map( 'absint', wp_list_pluck( $services, 'ID' ) );
	$eligible_staff = array();
	foreach ( $staff_members as $staff_member ) {
		$eligible = get_post_meta( $staff_member->ID, '_parisan_staff_services', true );
		$eligible = is_array( $eligible ) ? array_values( array_intersect( array_map( 'absint', $eligible ), $service_ids ) ) : array();
		if ( $eligible ) {
			$eligible_staff[] = array( 'post' => $staff_member, 'services' => $eligible );
		}
	}
	if ( ! $eligible_staff ) {
		return '<section class="parisan-booking-shell" dir="rtl"><p class="parisan-booking-notice">' . esc_html__( 'هنوز متخصصی برای خدمات منتشرشده تعیین نشده است.', 'parisan-core' ) . '</p></section>';
	}

	wp_enqueue_style( 'parisan-public-booking', PARISAN_CORE_URL . 'assets/css/public-booking.css', array(), PARISAN_CORE_VERSION );
	wp_enqueue_script( 'parisan-public-booking', PARISAN_CORE_URL . 'assets/js/public-booking.js', array(), PARISAN_CORE_VERSION, true );
	wp_localize_script(
		'parisan-public-booking',
		'ParisanPublicBooking',
		array(
			'restUrl' => esc_url_raw( rest_url( 'parisan/v1/' ) ),
			'strings' => array(
				'loading' => __( 'در حال بررسی…', 'parisan-core' ),
				'noSlots' => __( 'برای این تاریخ زمان آزادی پیدا نشد.', 'parisan-core' ),
				'selectTargets' => __( 'ابتدا خدمت، متخصص و تاریخ را انتخاب کنید.', 'parisan-core' ),
				'bookingCreated' => __( 'درخواست نوبت ثبت شد. توکن دسترسی را در جای امن نگه دارید.', 'parisan-core' ),
				'bookingLoaded' => __( 'اطلاعات رزرو دریافت شد.', 'parisan-core' ),
				'bookingCancelled' => __( 'رزرو لغو شد.', 'parisan-core' ),
				'rescheduled' => __( 'زمان نوبت با موفقیت تغییر کرد.', 'parisan-core' ),
				'customerSlotsLoaded' => __( 'زمان‌های آزاد برای تغییر نوبت بارگذاری شد.', 'parisan-core' ),
				'genericError' => __( 'انجام درخواست ممکن نشد؛ دوباره تلاش کنید.', 'parisan-core' ),
				'copySuccess' => __( 'توکن کپی شد.', 'parisan-core' ),
				'copyFailure' => __( 'کپی خودکار ممکن نشد؛ توکن را دستی کپی کنید.', 'parisan-core' ),
			),
		)
	);

	$today = current_datetime();
	$minimum_date = $today->format( 'Y-m-d' );
	$maximum_date = $today->modify( '+90 days' )->format( 'Y-m-d' );

	ob_start();
	?>
	<section class="parisan-booking-shell" dir="rtl" data-parisan-booking>
		<header class="parisan-booking-heading">
			<p class="parisan-booking-eyebrow"><?php esc_html_e( 'PARISAN APPOINTMENTS', 'parisan-core' ); ?></p>
			<h2><?php esc_html_e( 'رزرو نوبت', 'parisan-core' ); ?></h2>
			<p><?php esc_html_e( 'خدمت و متخصص را انتخاب کنید، زمان آزاد را ببینید و درخواست نوبت خود را ثبت کنید. ساعت‌ها بر اساس منطقهٔ زمانی سالن نمایش داده می‌شوند.', 'parisan-core' ); ?></p>
		</header>

		<form class="parisan-booking-form" data-parisan-create-form>
			<div class="parisan-booking-grid">
				<p class="parisan-booking-field">
					<label for="parisan-booking-service"><?php esc_html_e( 'خدمت', 'parisan-core' ); ?></label>
					<select id="parisan-booking-service" name="service_id" required>
						<option value=""><?php esc_html_e( 'انتخاب خدمت', 'parisan-core' ); ?></option>
						<?php foreach ( $services as $service ) : ?>
							<?php
							$price_type = get_post_meta( $service->ID, '_parisan_price_type', true );
							$service_label = get_the_title( $service );
							if ( 'from' === $price_type ) {
								$service_label .= ' — ' . __( 'نیازمند تعیین قیمت نهایی', 'parisan-core' );
							} else {
								$service_price = absint( get_post_meta( $service->ID, '_parisan_price', true ) );
								$service_duration = absint( get_post_meta( $service->ID, '_parisan_duration', true ) );
								$service_label .= ' — ' . number_format_i18n( $service_price ) . ' ' . __( 'تومان', 'parisan-core' ) . ' · ' . number_format_i18n( $service_duration ) . ' ' . __( 'دقیقه', 'parisan-core' );
							}
							?>
							<option value="<?php echo esc_attr( $service->ID ); ?>" <?php disabled( 'from', $price_type ); ?>><?php echo esc_html( $service_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="parisan-booking-field">
					<label for="parisan-booking-staff"><?php esc_html_e( 'متخصص', 'parisan-core' ); ?></label>
					<select id="parisan-booking-staff" name="staff_id" required disabled>
						<option value=""><?php esc_html_e( 'ابتدا خدمت را انتخاب کنید', 'parisan-core' ); ?></option>
						<?php foreach ( $eligible_staff as $entry ) : ?>
							<option value="<?php echo esc_attr( $entry['post']->ID ); ?>" data-services="<?php echo esc_attr( implode( ',', $entry['services'] ) ); ?>"><?php echo esc_html( get_the_title( $entry['post'] ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="parisan-booking-field">
					<label for="parisan-booking-date"><?php esc_html_e( 'تاریخ', 'parisan-core' ); ?></label>
					<input id="parisan-booking-date" name="date" type="date" min="<?php echo esc_attr( $minimum_date ); ?>" max="<?php echo esc_attr( $maximum_date ); ?>" required>
				</p>
				<p class="parisan-booking-field">
					<label for="parisan-booking-slot"><?php esc_html_e( 'ساعت‌های آزاد', 'parisan-core' ); ?></label>
					<select id="parisan-booking-slot" name="starts_at_local" required disabled>
						<option value=""><?php esc_html_e( 'ابتدا خدمت، متخصص و تاریخ را انتخاب کنید', 'parisan-core' ); ?></option>
					</select>
				</p>
				<p class="parisan-booking-field">
					<label for="parisan-booking-name"><?php esc_html_e( 'نام و نام خانوادگی', 'parisan-core' ); ?></label>
					<input id="parisan-booking-name" name="customer_name" type="text" maxlength="190" autocomplete="name" required>
				</p>
				<p class="parisan-booking-field">
					<label for="parisan-booking-phone"><?php esc_html_e( 'شماره تماس', 'parisan-core' ); ?></label>
					<input id="parisan-booking-phone" name="customer_phone" type="tel" maxlength="24" inputmode="tel" autocomplete="tel" required>
				</p>
				<p class="parisan-booking-field parisan-booking-field-wide">
					<label for="parisan-booking-email"><?php esc_html_e( 'ایمیل (اختیاری)', 'parisan-core' ); ?></label>
					<input id="parisan-booking-email" name="customer_email" type="email" maxlength="190" autocomplete="email">
				</p>
			</div>
			<p class="parisan-booking-privacy-note"><?php esc_html_e( 'اطلاعات تماس شما فقط برای ثبت و مدیریت درخواست نوبت استفاده می‌شود.', 'parisan-core' ); ?>
				<?php $privacy_url = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : ''; ?>
				<?php if ( $privacy_url ) : ?> <a href="<?php echo esc_url( $privacy_url ); ?>"><?php esc_html_e( 'مطالعهٔ سیاست حریم خصوصی', 'parisan-core' ); ?></a><?php endif; ?>
			</p>
			<button class="parisan-booking-button" type="submit"><?php esc_html_e( 'ثبت درخواست نوبت', 'parisan-core' ); ?></button>
		</form>

		<div class="parisan-booking-result" data-parisan-result hidden>
			<p class="parisan-booking-status" data-parisan-status role="status" aria-live="polite"></p>
			<div class="parisan-booking-token" data-parisan-token-panel hidden>
				<label for="parisan-customer-token"><?php esc_html_e( 'توکن محرمانهٔ دسترسی به رزرو', 'parisan-core' ); ?></label>
				<div class="parisan-booking-token-row">
					<input id="parisan-customer-token" type="text" data-parisan-token autocomplete="off" spellcheck="false" readonly>
					<button type="button" class="parisan-booking-button parisan-booking-button-secondary" data-parisan-copy><?php esc_html_e( 'کپی توکن', 'parisan-core' ); ?></button>
				</div>
				<p><?php esc_html_e( 'این توکن فقط یک‌بار صادر می‌شود. آن را در جای امن نگه دارید؛ هرگز آن را برای دیگران نفرستید.', 'parisan-core' ); ?></p>
			</div>
		</div>

		<section class="parisan-customer-access" aria-labelledby="parisan-customer-access-title">
			<h3 id="parisan-customer-access-title"><?php esc_html_e( 'پیگیری یا لغو رزرو', 'parisan-core' ); ?></h3>
			<p><?php esc_html_e( 'برای مشاهدهٔ رزرو، توکن محرمانه‌ای را که هنگام ثبت دریافت کردید وارد کنید.', 'parisan-core' ); ?></p>
			<form data-parisan-access-form>
				<label for="parisan-existing-token"><?php esc_html_e( 'توکن دسترسی', 'parisan-core' ); ?></label>
				<div class="parisan-booking-token-row">
					<input id="parisan-existing-token" type="password" inputmode="text" autocomplete="off" spellcheck="false" required data-parisan-existing-token>
					<button type="submit" class="parisan-booking-button parisan-booking-button-secondary"><?php esc_html_e( 'مشاهده رزرو', 'parisan-core' ); ?></button>
				</div>
			</form>
			<div class="parisan-customer-summary" data-parisan-summary hidden>
				<p data-parisan-summary-text></p>
				<button type="button" class="parisan-booking-button parisan-booking-button-danger" data-parisan-cancel hidden><?php esc_html_e( 'لغو رزرو', 'parisan-core' ); ?></button>
			</div>
			<div class="parisan-customer-reschedule" data-parisan-reschedule-panel hidden>
				<h4><?php esc_html_e( 'تغییر زمان نوبت', 'parisan-core' ); ?></h4>
				<form data-parisan-reschedule-form>
					<div class="parisan-booking-grid">
						<p class="parisan-booking-field">
							<label for="parisan-customer-reschedule-date"><?php esc_html_e( 'تاریخ جدید', 'parisan-core' ); ?></label>
							<input id="parisan-customer-reschedule-date" type="date" min="<?php echo esc_attr( $minimum_date ); ?>" max="<?php echo esc_attr( $maximum_date ); ?>" required data-parisan-reschedule-date>
						</p>
						<p class="parisan-booking-field">
							<label for="parisan-customer-reschedule-slot"><?php esc_html_e( 'ساعت جدید', 'parisan-core' ); ?></label>
							<select id="parisan-customer-reschedule-slot" required disabled data-parisan-reschedule-slot>
								<option value=""><?php esc_html_e( 'ابتدا تاریخ را انتخاب کنید', 'parisan-core' ); ?></option>
							</select>
						</p>
					</div>
					<button type="submit" class="parisan-booking-button parisan-booking-button-secondary"><?php esc_html_e( 'ثبت زمان جدید', 'parisan-core' ); ?></button>
				</form>
			</div>
		</section>
		<p class="parisan-booking-status" data-parisan-live role="status" aria-live="polite"></p>
	</section>
	<?php
	return ob_get_clean();
}
add_shortcode( 'parisan_booking', 'parisan_core_booking_shortcode' );
