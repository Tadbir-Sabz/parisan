<?php
/**
 * Verify explicit public API enablement and the Persian booking shortcode.
 *
 * Run only against disposable WordPress with PARISAN_PUBLIC_BOOKING_ENABLED=true.
 *
 * @package ParisanCore
 */

defined( 'ABSPATH' ) || exit;

function parisan_public_enabled_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

parisan_public_enabled_assert( true === parisan_core_public_booking_enabled(), 'explicit feature flag enables the public booking module' );
do_action( 'rest_api_init', rest_get_server() );
$routes = rest_get_server()->get_routes();
foreach ( array( '/parisan/v1/availability', '/parisan/v1/bookings', '/parisan/v1/customer-booking', '/parisan/v1/customer-booking/cancel', '/parisan/v1/customer-booking/availability', '/parisan/v1/customer-booking/reschedule' ) as $route ) {
	parisan_public_enabled_assert( isset( $routes[ $route ] ), 'expected gated route is registered: ' . $route );
}

$service_id = wp_insert_post(
	array(
		'post_type' => 'parisan_service',
		'post_status' => 'publish',
		'post_title' => 'Synthetic Public Booking Service',
	),
	true
);
parisan_public_enabled_assert( ! is_wp_error( $service_id ), 'synthetic public service is created' );
update_post_meta( $service_id, '_parisan_duration', 45 );
update_post_meta( $service_id, '_parisan_price', 150000 );
update_post_meta( $service_id, '_parisan_price_type', 'fixed' );

$staff_id = wp_insert_post(
	array(
		'post_type' => 'parisan_staff',
		'post_status' => 'publish',
		'post_title' => 'Synthetic Public Booking Specialist',
	),
	true
);
parisan_public_enabled_assert( ! is_wp_error( $staff_id ), 'synthetic public specialist is created' );
update_post_meta( $staff_id, '_parisan_staff_services', array( (int) $service_id ) );

$html = parisan_core_booking_shortcode();
parisan_public_enabled_assert( false !== strpos( $html, 'data-parisan-booking' ), 'booking shortcode renders its accessible root' );
parisan_public_enabled_assert( false !== strpos( $html, 'data-parisan-create-form' ), 'booking shortcode renders the create form' );
parisan_public_enabled_assert( false !== strpos( $html, 'data-parisan-reschedule-form' ), 'booking shortcode renders token-based customer rescheduling controls' );
parisan_public_enabled_assert( false !== strpos( $html, 'Synthetic Public Booking Service' ), 'published services are offered in the wizard' );
parisan_public_enabled_assert( false !== strpos( $html, 'تومان' ) && false !== strpos( $html, 'دقیقه' ), 'wizard displays fixed service price and duration before booking' );
parisan_public_enabled_assert( false !== strpos( $html, 'Synthetic Public Booking Specialist' ), 'eligible specialists are offered in the wizard' );
parisan_public_enabled_assert( false !== strpos( $html, 'اطلاعات تماس شما فقط برای ثبت و مدیریت درخواست نوبت استفاده می‌شود' ), 'booking wizard discloses the purpose of customer contact data' );
parisan_public_enabled_assert( wp_script_is( 'parisan-public-booking', 'enqueued' ), 'public booking JavaScript is enqueued' );
parisan_public_enabled_assert( wp_style_is( 'parisan-public-booking', 'enqueued' ), 'public booking CSS is enqueued' );

update_post_meta( $staff_id, '_parisan_staff_weekly_schedule', array( 1 => array( 'start' => '09:00', 'end' => '17:00' ) ) );
update_option( 'timezone_string', 'UTC' );
$booking_date = current_datetime()->modify( 'next monday' )->format( 'Y-m-d' );
$base_input = array(
	'staff_id' => (int) $staff_id,
	'service_id' => (int) $service_id,
	'starts_at_local' => $booking_date . ' 10:00',
	'customer_name' => 'Synthetic Booking Customer',
	'customer_phone' => '09123456789',
	'customer_email' => 'public-booking@example.test',
	'idempotency_token' => str_repeat( 'a', 64 ),
	'customer_access_token' => bin2hex( random_bytes( 32 ) ),
);
$first_customer_token = $base_input['customer_access_token'];
$first_booking = parisan_core_create_booking( $base_input );
parisan_public_enabled_assert( ! is_wp_error( $first_booking ), 'public booking service creates a pending booking with scoped token' );

$conflicting_input = $base_input;
$conflicting_input['customer_name'] = 'Different Synthetic Customer';
$conflicting_input['customer_access_token'] = bin2hex( random_bytes( 32 ) );
$conflict_token = $conflicting_input['customer_access_token'];
$conflict_result = parisan_core_create_booking( $conflicting_input );
parisan_public_enabled_assert( is_wp_error( $conflict_result ) && 'parisan_idempotency_conflict' === $conflict_result->get_error_code(), 'idempotency key cannot be reused with different customer data' );
parisan_public_enabled_assert( is_array( parisan_core_resolve_customer_access_token( $first_customer_token ) ), 'idempotency conflict cannot rotate or revoke the original customer token' );
parisan_public_enabled_assert( false === parisan_core_resolve_customer_access_token( $conflict_token ), 'conflicting request token is never stored' );

$retry_input = $base_input;
$retry_input['customer_access_token'] = bin2hex( random_bytes( 32 ) );
$replacement_customer_token = $retry_input['customer_access_token'];
$retry_booking = parisan_core_create_booking( $retry_input );
parisan_public_enabled_assert( ! is_wp_error( $retry_booking ) && ! empty( $retry_booking['reused'] ), 'valid idempotent retry reuses the original booking' );
parisan_public_enabled_assert( false === parisan_core_resolve_customer_access_token( $first_customer_token ), 'valid idempotent retry rotates and revokes the previous token' );
parisan_public_enabled_assert( is_array( parisan_core_resolve_customer_access_token( $replacement_customer_token ) ), 'valid idempotent retry stores the replacement customer token' );

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$availability_request = new WP_REST_Request( 'GET', '/parisan/v1/availability' );
$availability_request->set_param( 'staff_id', (int) $staff_id );
$availability_request->set_param( 'service_id', (int) $service_id );
$availability_request->set_param( 'date', $booking_date );
$availability_response = parisan_core_rest_public_availability( $availability_request );
parisan_public_enabled_assert( 200 === $availability_response->get_status(), 'public availability route returns candidate slots for a valid date' );
$available_times = wp_list_pluck( $availability_response->get_data()['slots'], 'starts_at_local' );
parisan_public_enabled_assert( ! in_array( $booking_date . ' 10:00', $available_times, true ) && in_array( $booking_date . ' 11:00', $available_times, true ), 'public availability omits the occupied slot and retains a free slot' );

$availability_horizon_request = new WP_REST_Request( 'GET', '/parisan/v1/availability' );
$availability_horizon_request->set_param( 'staff_id', (int) $staff_id );
$availability_horizon_request->set_param( 'service_id', (int) $service_id );
$availability_horizon_request->set_param( 'date', current_datetime()->modify( '+91 days' )->format( 'Y-m-d' ) );
$availability_horizon_response = parisan_core_rest_public_availability( $availability_horizon_request );
parisan_public_enabled_assert( 400 === $availability_horizon_response->get_status(), 'public availability route enforces the 90-day horizon' );

$invalid_request = new WP_REST_Request( 'POST', '/parisan/v1/bookings' );
$invalid_request->set_header( 'Origin', home_url( '/' ) );
$invalid_request->set_header( 'Idempotency-Key', str_repeat( 'a', 32 ) );
$invalid_key_response = parisan_core_rest_public_create_booking( $invalid_request );
parisan_public_enabled_assert( 400 === $invalid_key_response->get_status(), 'public route rejects idempotency keys below 256 bits' );

$horizon_request = new WP_REST_Request( 'POST', '/parisan/v1/bookings' );
$horizon_request->set_header( 'Origin', home_url( '/' ) );
$horizon_request->set_header( 'Idempotency-Key', bin2hex( random_bytes( 32 ) ) );
$horizon_request->set_param( 'starts_at_local', current_datetime()->modify( '+91 days' )->format( 'Y-m-d' ) . ' 10:00' );
$horizon_response = parisan_core_rest_public_create_booking( $horizon_request );
parisan_public_enabled_assert( 400 === $horizon_response->get_status(), 'public create route enforces the 90-day booking horizon' );

$public_request = new WP_REST_Request( 'POST', '/parisan/v1/bookings' );
$public_request->set_header( 'Origin', home_url( '/' ) );
$public_request->set_header( 'Idempotency-Key', bin2hex( random_bytes( 32 ) ) );
$public_request->set_param( 'staff_id', (int) $staff_id );
$public_request->set_param( 'service_id', (int) $service_id );
$public_request->set_param( 'starts_at_local', $booking_date . ' 11:00' );
$public_request->set_param( 'customer_name', 'Synthetic REST Customer' );
$public_request->set_param( 'customer_phone', '09123456780' );
$public_response = parisan_core_rest_public_create_booking( $public_request );
parisan_public_enabled_assert( 201 === $public_response->get_status(), 'same-origin public booking route returns a created pending booking' );
$public_response_data = $public_response->get_data();
parisan_public_enabled_assert( isset( $public_response_data['customer_access_token'] ) && 64 === strlen( $public_response_data['customer_access_token'] ), 'public create response returns one 256-bit customer token' );
parisan_public_enabled_assert( is_array( parisan_core_resolve_customer_access_token( $public_response_data['customer_access_token'] ) ), 'public create response token is stored as a resolvable digest' );

$customer_token = $public_response_data['customer_access_token'];
$customer_availability_request = new WP_REST_Request( 'GET', '/parisan/v1/customer-booking/availability' );
$customer_availability_request->set_header( 'X-Parisan-Booking-Token', $customer_token );
$customer_availability_request->set_param( 'date', $booking_date );
$customer_availability_response = parisan_core_rest_customer_availability( $customer_availability_request );
parisan_public_enabled_assert( 200 === $customer_availability_response->get_status(), 'customer-token availability route returns reschedule candidates' );
$customer_available_times = wp_list_pluck( $customer_availability_response->get_data()['slots'], 'starts_at_local' );
parisan_public_enabled_assert( ! in_array( $booking_date . ' 11:00', $customer_available_times, true ) && in_array( $booking_date . ' 12:00', $customer_available_times, true ), 'customer availability excludes the current booking while preserving free times; actual=' . implode( ',', $customer_available_times ) );

$customer_reschedule_request = new WP_REST_Request( 'POST', '/parisan/v1/customer-booking/reschedule' );
$customer_reschedule_request->set_header( 'Origin', home_url( '/' ) );
$customer_reschedule_request->set_header( 'X-Parisan-Booking-Token', $customer_token );
$customer_reschedule_request->set_param( 'starts_at_local', $booking_date . ' 12:00' );
$customer_reschedule_response = parisan_core_rest_customer_reschedule_booking( $customer_reschedule_request );
parisan_public_enabled_assert( 200 === $customer_reschedule_response->get_status(), 'customer can reschedule a token-owned pending booking' );

$customer_view_request = new WP_REST_Request( 'GET', '/parisan/v1/customer-booking' );
$customer_view_request->set_header( 'X-Parisan-Booking-Token', $customer_token );
$customer_view_response = parisan_core_rest_customer_booking( $customer_view_request );
$customer_view_data = $customer_view_response->get_data();
parisan_public_enabled_assert( 200 === $customer_view_response->get_status() && $booking_date . ' 12:00' === $customer_view_data['starts_at_local_display'] && true === $customer_view_data['can_reschedule'], 'customer view reflects the new local appointment time and reschedule capability' );

$customer_booking = parisan_core_resolve_customer_access_token( $customer_token );
$customer_token_row = $wpdb->get_row( $wpdb->prepare( "SELECT expires_at_utc FROM {$wpdb->prefix}parisan_customer_tokens WHERE booking_id = %d AND revoked_at_utc IS NULL", (int) $customer_booking['id'] ), ARRAY_A );
$expected_customer_expiry = ( new DateTimeImmutable( $booking_date . ' 12:45:00', new DateTimeZone( 'UTC' ) ) )->modify( '+30 days' )->format( 'Y-m-d H:i:s' );
parisan_public_enabled_assert( is_array( $customer_token_row ) && $expected_customer_expiry === $customer_token_row['expires_at_utc'], 'customer reschedule moves token expiry to 30 days after the new appointment end' );

$customer_reschedule_horizon = new WP_REST_Request( 'POST', '/parisan/v1/customer-booking/reschedule' );
$customer_reschedule_horizon->set_header( 'Origin', home_url( '/' ) );
$customer_reschedule_horizon->set_header( 'X-Parisan-Booking-Token', $customer_token );
$customer_reschedule_horizon->set_param( 'starts_at_local', current_datetime()->modify( '+91 days' )->format( 'Y-m-d' ) . ' 12:00' );
$customer_reschedule_horizon_response = parisan_core_rest_customer_reschedule_booking( $customer_reschedule_horizon );
parisan_public_enabled_assert( 400 === $customer_reschedule_horizon_response->get_status(), 'customer reschedule route enforces the 90-day horizon' );

$invalid_customer_reschedule = new WP_REST_Request( 'POST', '/parisan/v1/customer-booking/reschedule' );
$invalid_customer_reschedule->set_header( 'Origin', home_url( '/' ) );
$invalid_customer_reschedule->set_header( 'X-Parisan-Booking-Token', bin2hex( random_bytes( 32 ) ) );
$invalid_customer_reschedule->set_param( 'starts_at_local', $booking_date . ' 13:00' );
$invalid_customer_reschedule_response = parisan_core_rest_customer_reschedule_booking( $invalid_customer_reschedule );
parisan_public_enabled_assert( 404 === $invalid_customer_reschedule_response->get_status(), 'customer rescheduling rejects an invalid or unowned token' );

$cross_origin_reschedule = new WP_REST_Request( 'POST', '/parisan/v1/customer-booking/reschedule' );
$cross_origin_reschedule->set_header( 'Origin', 'https://attacker.invalid' );
$cross_origin_reschedule->set_header( 'X-Parisan-Booking-Token', $customer_token );
$cross_origin_reschedule->set_param( 'starts_at_local', $booking_date . ' 13:00' );
$cross_origin_response = parisan_core_rest_customer_reschedule_booking( $cross_origin_reschedule );
parisan_public_enabled_assert( 403 === $cross_origin_response->get_status(), 'customer rescheduling rejects cross-origin mutations' );


echo "PASS: explicit public API enablement, route registration, and Persian booking shortcode rendering.\n";
