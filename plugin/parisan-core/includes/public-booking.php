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
			"SELECT id, public_id, service_id, staff_id, starts_at_utc, ends_at_utc, salon_timezone, status, price_amount, currency_code FROM {$bookings_table} WHERE id = %d LIMIT 1",
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
		return parisan_core_public_booking_error( __( 'درخواست‌های زیادی ثبت شده است؛ کمی بعد دوباره تلاش کنید.', 'parisan-core' ), 429 );
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

	$idempotency = $request->get_header( 'idempotency-key' );
	if ( ! is_string( $idempotency ) || ! preg_match( '/^[A-Za-z0-9._~-]{32,128}$/D', $idempotency ) ) {
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
		'price_amount' => (int) $booking['price_amount'],
		'currency_code' => $booking['currency_code'],
		'can_cancel' => in_array( $booking['status'], array( 'pending', 'confirmed' ), true ),
	);
	return parisan_core_private_booking_response( new WP_REST_Response( $data, 200 ) );
}

/**
 * Cancel an appointment using only its scoped customer token.
 *
 * @param int $booking_id Booking ID.
 * @return true|WP_Error
 */
function parisan_core_cancel_booking_with_customer_token( $booking_id ) {
	global $wpdb;

	$bookings_table = $wpdb->prefix . 'parisan_bookings';
	$events_table = $wpdb->prefix . 'parisan_booking_events';
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
	$result = parisan_core_cancel_booking_with_customer_token( (int) $booking['id'] );
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
		'/customer-booking/cancel',
		array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => 'parisan_core_rest_customer_cancel_booking',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'parisan_core_register_public_booking_routes' );
