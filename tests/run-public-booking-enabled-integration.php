<?php
/**
 * Verify explicit public API enablement and the Persian booking shortcode.
 *
 * Run only against disposable WordPress with PARISAN_PUBLIC_BOOKING_ENABLED=true.
 *
 * @package ParisanCore
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

function parisan_public_enabled_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

parisan_public_enabled_assert( true === parisan_core_public_booking_enabled(), 'explicit feature flag enables the public booking module' );

global $wp_query;
if ( ! $wp_query instanceof WP_Query ) {
	$wp_query = new WP_Query();
}
$wp_query->is_front_page = true;
do_action( 'wp_enqueue_scripts' );
parisan_public_enabled_assert( wp_style_is( 'parisan-public-booking', 'enqueued' ), 'public booking stylesheet is enqueued before wp_head on the homepage' );
parisan_public_enabled_assert( wp_script_is( 'parisan-public-booking', 'enqueued' ), 'public booking script is enqueued before wp_head on the homepage' );

ob_start();
wp_head();
$booking_head_html = ob_get_clean();
parisan_public_enabled_assert( false !== strpos( $booking_head_html, 'public-booking.css' ), 'public booking stylesheet is printed in the document head' );

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
parisan_public_enabled_assert( shortcode_exists( 'parisan_waitlist' ), 'consent-based customer waitlist shortcode is registered' );
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
	'customer_email_consent' => true,
	'idempotency_token' => str_repeat( 'a', 64 ),
	'customer_access_token' => bin2hex( random_bytes( 32 ) ),
);
$first_customer_token = $base_input['customer_access_token'];
$first_booking = parisan_core_create_booking( $base_input );
parisan_public_enabled_assert( ! is_wp_error( $first_booking ), 'public booking service creates a pending booking with scoped token' );
$first_consent = (int) $wpdb->get_var( $wpdb->prepare( "SELECT customer_email_consent FROM {$wpdb->prefix}parisan_bookings WHERE id=%d", (int) $first_booking['booking_id'] ) );
$first_reminders = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}parisan_booking_reminders WHERE booking_id=%d", (int) $first_booking['booking_id'] ) );
parisan_public_enabled_assert( 1 === $first_consent && 2 === $first_reminders, 'explicit email consent stores one 24-hour and one 2-hour reminder record' );

$resource_one = wp_insert_post( array( 'post_type' => 'parisan_resource', 'post_status' => 'publish', 'post_title' => 'Synthetic Treatment Bed A' ), true );
$resource_two = wp_insert_post( array( 'post_type' => 'parisan_resource', 'post_status' => 'publish', 'post_title' => 'Synthetic Treatment Bed B' ), true );
parisan_public_enabled_assert( ! is_wp_error( $resource_one ) && ! is_wp_error( $resource_two ), 'synthetic shared treatment resources are created' );
update_post_meta( $service_id, '_parisan_service_resources', array( (int) $resource_one, (int) $resource_two ) );
parisan_public_enabled_assert( array( (int) $resource_one, (int) $resource_two ) === parisan_core_get_service_resource_ids( $service_id ), 'service resource pool resolves only published resources in stable order' );

$resource_staff_ids = array();
foreach ( array( 'B', 'C', 'D' ) as $suffix ) {
	$resource_staff = wp_insert_post( array( 'post_type' => 'parisan_staff', 'post_status' => 'publish', 'post_title' => 'Synthetic Resource Specialist ' . $suffix ), true );
	parisan_public_enabled_assert( ! is_wp_error( $resource_staff ), 'additional synthetic resource specialist is created' );
	update_post_meta( $resource_staff, '_parisan_staff_services', array( (int) $service_id ) );
	update_post_meta( $resource_staff, '_parisan_staff_weekly_schedule', array( 1 => array( 'start' => '09:00', 'end' => '17:00' ) ) );
	$resource_staff_ids[] = (int) $resource_staff;
}
$resource_booking_input = $base_input;
$resource_booking_input['staff_id'] = $resource_staff_ids[0];
$resource_booking_input['starts_at_local'] = $booking_date . ' 11:00';
$resource_booking_input['idempotency_token'] = str_repeat( 'c', 64 );
$resource_booking_input['customer_access_token'] = '';
$resource_booking_one = parisan_core_create_booking( $resource_booking_input );
parisan_public_enabled_assert( ! is_wp_error( $resource_booking_one ), 'first specialist reserves the first free shared resource' );
$resource_booking_reminders = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}parisan_booking_reminders WHERE booking_id=%d", (int) $resource_booking_one['booking_id'] ) );
parisan_public_enabled_assert( 0 === $resource_booking_reminders, 'bookings without explicit email consent never receive reminder queue records' );
$bookings_table = $wpdb->prefix . 'parisan_bookings';
$assigned_one = (int) $wpdb->get_var( $wpdb->prepare( "SELECT resource_id FROM {$bookings_table} WHERE id = %d", (int) $resource_booking_one['booking_id'] ) );
parisan_public_enabled_assert( (int) $resource_one === $assigned_one, 'booking persists the selected resource id' );

$resource_booking_input['staff_id'] = $resource_staff_ids[1];
$resource_booking_input['idempotency_token'] = str_repeat( 'd', 64 );
$resource_booking_two = parisan_core_create_booking( $resource_booking_input );
parisan_public_enabled_assert( ! is_wp_error( $resource_booking_two ), 'second specialist can use the second free resource at the same time' );
$assigned_two = (int) $wpdb->get_var( $wpdb->prepare( "SELECT resource_id FROM {$bookings_table} WHERE id = %d", (int) $resource_booking_two['booking_id'] ) );
parisan_public_enabled_assert( (int) $resource_two === $assigned_two, 'parallel service capacity assigns a distinct resource to the second specialist' );

$resource_booking_input['staff_id'] = $resource_staff_ids[2];
$resource_booking_input['idempotency_token'] = str_repeat( 'e', 64 );
$resource_exhausted = parisan_core_create_booking( $resource_booking_input );
parisan_public_enabled_assert( is_wp_error( $resource_exhausted ) && 'parisan_booking_slot_unavailable' === $resource_exhausted->get_error_code(), 'booking is rejected when all shared resources are occupied despite another free specialist' );

wp_update_post( array( 'ID' => (int) $resource_one, 'post_status' => 'draft' ) );
wp_update_post( array( 'ID' => (int) $resource_two, 'post_status' => 'draft' ) );
$future_resource_date = $booking_date;
$inactive_resource_slots = parisan_core_get_available_slots( $resource_staff_ids[2], $service_id, $future_resource_date );
parisan_public_enabled_assert( is_array( $inactive_resource_slots ) && empty( $inactive_resource_slots ), 'service with a configured but fully inactive resource pool fails closed' );
wp_update_post( array( 'ID' => (int) $resource_one, 'post_status' => 'publish' ) );
wp_update_post( array( 'ID' => (int) $resource_two, 'post_status' => 'publish' ) );

$shared_service_id = wp_insert_post( array( 'post_type' => 'parisan_service', 'post_status' => 'publish', 'post_title' => 'Synthetic Shared Resource Service' ), true );
parisan_public_enabled_assert( ! is_wp_error( $shared_service_id ), 'synthetic second service is created for shared-resource legacy coverage' );
update_post_meta( $shared_service_id, '_parisan_duration', 45 );
update_post_meta( $shared_service_id, '_parisan_price', 120000 );
update_post_meta( $shared_service_id, '_parisan_price_type', 'fixed' );
$shared_staff_id = wp_insert_post( array( 'post_type' => 'parisan_staff', 'post_status' => 'publish', 'post_title' => 'Synthetic Shared Resource Specialist' ), true );
parisan_public_enabled_assert( ! is_wp_error( $shared_staff_id ), 'synthetic specialist for the second service is created' );
update_post_meta( $shared_staff_id, '_parisan_staff_services', array( (int) $shared_service_id ) );
update_post_meta( $shared_staff_id, '_parisan_staff_weekly_schedule', array( 1 => array( 'start' => '09:00', 'end' => '17:00' ) ) );
$legacy_shared_input = $base_input;
$legacy_shared_input['service_id'] = (int) $shared_service_id;
$legacy_shared_input['staff_id'] = (int) $shared_staff_id;
$legacy_shared_input['starts_at_local'] = $booking_date . ' 13:00';
$legacy_shared_input['idempotency_token'] = str_repeat( 'f', 64 );
$legacy_shared_input['customer_access_token'] = '';
$legacy_shared_booking = parisan_core_create_booking( $legacy_shared_input );
parisan_public_enabled_assert( ! is_wp_error( $legacy_shared_booking ), 'legacy booking is created before the second service is assigned a shared resource' );
update_post_meta( $shared_service_id, '_parisan_service_resources', array( (int) $resource_one ) );
$shared_resource_slots = parisan_core_get_available_slots( $resource_staff_ids[2], $service_id, $booking_date );
$shared_slot = null;
foreach ( $shared_resource_slots as $candidate_slot ) {
	if ( $booking_date . ' 13:00' === $candidate_slot['starts_at_local'] ) {
		$shared_slot = $candidate_slot;
		break;
	}
}
parisan_public_enabled_assert( is_array( $shared_slot ) && (int) $resource_two === (int) $shared_slot['resource_id'], 'legacy booking of another service sharing resource A leaves only resource B available' );

$bundle_room = wp_insert_post( array( 'post_type' => 'parisan_resource', 'post_status' => 'publish', 'post_title' => 'Synthetic Bundle Room' ), true );
$bundle_device = wp_insert_post( array( 'post_type' => 'parisan_resource', 'post_status' => 'publish', 'post_title' => 'Synthetic Bundle Device' ), true );
parisan_public_enabled_assert( ! is_wp_error( $bundle_room ) && ! is_wp_error( $bundle_device ), 'room and device resources for a bundle are created' );
update_post_meta( $bundle_room, '_parisan_resource_type', 'room' );
update_post_meta( $bundle_device, '_parisan_resource_type', 'device' );
$bundle_service = wp_insert_post( array( 'post_type' => 'parisan_service', 'post_status' => 'publish', 'post_title' => 'Synthetic Room And Device Service' ), true );
parisan_public_enabled_assert( ! is_wp_error( $bundle_service ), 'synthetic room-and-device service is created' );
update_post_meta( $bundle_service, '_parisan_duration', 45 );
update_post_meta( $bundle_service, '_parisan_price', 180000 );
update_post_meta( $bundle_service, '_parisan_price_type', 'fixed' );
update_post_meta( $bundle_service, '_parisan_service_resources', array( (int) $bundle_room, (int) $bundle_device ) );
$bundle_staff = wp_insert_post( array( 'post_type' => 'parisan_staff', 'post_status' => 'publish', 'post_title' => 'Synthetic Bundle Specialist' ), true );
parisan_public_enabled_assert( ! is_wp_error( $bundle_staff ), 'specialist for bundle service is created' );
update_post_meta( $bundle_staff, '_parisan_staff_services', array( (int) $bundle_service ) );
update_post_meta( $bundle_staff, '_parisan_staff_weekly_schedule', array( 1 => array( 'start' => '09:00', 'end' => '17:00' ) ) );
$bundle_slots = parisan_core_get_available_slots( (int) $bundle_staff, (int) $bundle_service, $booking_date );
$bundle_slot = null;
foreach ( is_array( $bundle_slots ) ? $bundle_slots : array() as $candidate ) {
	if ( $booking_date . ' 15:00' === $candidate['starts_at_local'] ) {
		$bundle_slot = $candidate;
		break;
	}
}
parisan_public_enabled_assert( is_array( $bundle_slot ) && 2 === count( $bundle_slot['resource_ids'] ) && in_array( (int) $bundle_room, $bundle_slot['resource_ids'], true ) && in_array( (int) $bundle_device, $bundle_slot['resource_ids'], true ), 'availability requires one room and one device simultaneously' );
$bundle_input = $base_input;
$bundle_input['service_id'] = (int) $bundle_service;
$bundle_input['staff_id'] = (int) $bundle_staff;
$bundle_input['starts_at_local'] = $booking_date . ' 15:00';
$bundle_input['idempotency_token'] = str_repeat( '9', 64 );
$bundle_input['customer_access_token'] = '';
$bundle_booking = parisan_core_create_booking( $bundle_input );
parisan_public_enabled_assert( ! is_wp_error( $bundle_booking ), 'booking creation persists a simultaneous room-and-device bundle' );
$bundle_assignments = parisan_core_get_booking_resource_ids( (int) $bundle_booking['booking_id'] );
sort( $bundle_assignments, SORT_NUMERIC );
$expected_bundle = array( (int) $bundle_room, (int) $bundle_device );
sort( $expected_bundle, SORT_NUMERIC );
parisan_public_enabled_assert( $expected_bundle === $bundle_assignments, 'booking resource relation stores both assigned resources' );
$bundle_reschedule = parisan_core_reschedule_booking( (int) $bundle_booking['booking_id'], $booking_date . ' 16:00', 'bundle reschedule integration' );
parisan_public_enabled_assert( ! is_wp_error( $bundle_reschedule ) && $expected_bundle === ( $actual_bundle = parisan_core_get_booking_resource_ids( (int) $bundle_booking['booking_id'] ) ), 'rescheduling preserves and atomically reassigns the complete resource bundle' );

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
parisan_public_enabled_assert( ! in_array( $booking_date . ' 10:00', $available_times, true ) && ! in_array( $booking_date . ' 11:00', $available_times, true ) && in_array( $booking_date . ' 12:00', $available_times, true ), 'public availability omits staff-occupied and resource-exhausted slots while retaining a free slot' );

update_option( 'parisan_booking_lead_time_minutes', 60 );
update_option( 'parisan_booking_horizon_days', 3 );
$window_now = new DateTimeImmutable( '2026-01-01 10:00:00', new DateTimeZone( 'UTC' ) );
$window_slots = array(
	array( 'starts_at_utc' => '2026-01-01 10:59:59', 'starts_at_local' => '2026-01-01 10:59' ),
	array( 'starts_at_utc' => '2026-01-01 11:00:00', 'starts_at_local' => '2026-01-01 11:00' ),
	array( 'starts_at_utc' => '2026-01-04 10:00:00', 'starts_at_local' => '2026-01-04 10:00' ),
	array( 'starts_at_utc' => '2026-01-04 10:00:01', 'starts_at_local' => '2026-01-04 10:00' ),
);
$window_filtered = parisan_core_filter_booking_window_slots( $window_slots, $window_now );
parisan_public_enabled_assert( 2 === count( $window_filtered ) && '2026-01-01 11:00' === $window_filtered[0]['starts_at_local'] && '2026-01-04 10:00' === $window_filtered[1]['starts_at_local'], 'lead-time and horizon filter enforce exact inclusive boundaries' );
parisan_public_enabled_assert( 60 === parisan_core_sanitize_booking_lead_time( 60 ) && 10080 === parisan_core_sanitize_booking_lead_time( 99999 ), 'lead-time setting is bounded to seven days' );
parisan_public_enabled_assert( 1 === parisan_core_sanitize_booking_horizon( 0 ) && 365 === parisan_core_sanitize_booking_horizon( 999 ), 'booking horizon is bounded to 1–365 days' );
update_option( 'parisan_booking_lead_time_minutes', 0 );
update_option( 'parisan_booking_horizon_days', 90 );

$tomorrow = current_datetime()->modify( '+1 day' );
$tomorrow_day = (int) $tomorrow->format( 'N' );
$lead_test_schedule = array( 1 => array( 'start' => '09:00', 'end' => '17:00' ) );
$lead_test_schedule[ $tomorrow_day ] = array( 'start' => '09:00', 'end' => '17:00' );
update_post_meta( $staff_id, '_parisan_staff_weekly_schedule', $lead_test_schedule );
update_option( 'parisan_booking_lead_time_minutes', 10080 );
$too_soon_input = $base_input;
$too_soon_input['starts_at_local'] = $tomorrow->format( 'Y-m-d' ) . ' 10:00';
$too_soon_input['idempotency_token'] = str_repeat( 'b', 64 );
$too_soon_input['customer_access_token'] = bin2hex( random_bytes( 32 ) );
$too_soon_result = parisan_core_create_booking( $too_soon_input );
parisan_public_enabled_assert( is_wp_error( $too_soon_result ) && 'parisan_booking_slot_unavailable' === $too_soon_result->get_error_code(), 'transactional booking creation rejects appointments inside the configured lead time' );
update_option( 'parisan_booking_lead_time_minutes', 0 );

$availability_horizon_request = new WP_REST_Request( 'GET', '/parisan/v1/availability' );
$availability_horizon_request->set_param( 'staff_id', (int) $staff_id );
$availability_horizon_request->set_param( 'service_id', (int) $service_id );
update_option( 'parisan_booking_horizon_days', 30 );
$availability_horizon_request->set_param( 'date', current_datetime()->modify( '+31 days' )->format( 'Y-m-d' ) );
$availability_horizon_response = parisan_core_rest_public_availability( $availability_horizon_request );
parisan_public_enabled_assert( 400 === $availability_horizon_response->get_status(), 'public availability route enforces the owner-configured 30-day horizon' );
update_option( 'parisan_booking_horizon_days', 90 );

$invalid_request = new WP_REST_Request( 'POST', '/parisan/v1/bookings' );
$invalid_request->set_header( 'Origin', home_url( '/' ) );
$invalid_request->set_header( 'Idempotency-Key', str_repeat( 'a', 32 ) );
$invalid_key_response = parisan_core_rest_public_create_booking( $invalid_request );
parisan_public_enabled_assert( 400 === $invalid_key_response->get_status(), 'public route rejects idempotency keys below 256 bits' );

$horizon_request = new WP_REST_Request( 'POST', '/parisan/v1/bookings' );
$horizon_request->set_header( 'Origin', home_url( '/' ) );
$horizon_request->set_header( 'Idempotency-Key', bin2hex( random_bytes( 32 ) ) );
update_option( 'parisan_booking_horizon_days', 30 );
$horizon_request->set_param( 'starts_at_local', current_datetime()->modify( '+31 days' )->format( 'Y-m-d' ) . ' 10:00' );
$horizon_response = parisan_core_rest_public_create_booking( $horizon_request );
parisan_public_enabled_assert( 400 === $horizon_response->get_status(), 'public create route enforces the owner-configured 30-day booking horizon' );
update_option( 'parisan_booking_horizon_days', 90 );

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
parisan_public_enabled_assert( in_array( $booking_date . ' 11:00', $customer_available_times, true ) && in_array( $booking_date . ' 12:00', $customer_available_times, true ), 'customer reschedule availability excludes its own booking from conflicts and offers the original and another free time' );

$customer_reschedule_request = new WP_REST_Request( 'POST', '/parisan/v1/customer-booking/reschedule' );
$customer_reschedule_request->set_header( 'Origin', home_url( '/' ) );
$customer_reschedule_request->set_header( 'X-Parisan-Booking-Token', $customer_token );
$customer_reschedule_request->set_param( 'starts_at_local', $booking_date . ' 12:00' );
$customer_reschedule_response = parisan_core_rest_customer_reschedule_booking( $customer_reschedule_request );
parisan_public_enabled_assert( 200 === $customer_reschedule_response->get_status(), 'customer can reschedule a token-owned pending booking' );
$expected_two_hour_reminder = $booking_date . ' 10:00:00';
$actual_two_hour_reminder = $wpdb->get_var( $wpdb->prepare( "SELECT scheduled_at_utc FROM {$wpdb->prefix}parisan_booking_reminders WHERE booking_id=%d AND reminder_kind='2h'", (int) $first_booking['booking_id'] ) );
parisan_public_enabled_assert( $expected_two_hour_reminder === $actual_two_hour_reminder, 'rescheduling recalculates the pending two-hour reminder against the new appointment time' );

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
update_option( 'parisan_booking_horizon_days', 30 );
$customer_reschedule_horizon->set_param( 'starts_at_local', current_datetime()->modify( '+31 days' )->format( 'Y-m-d' ) . ' 12:00' );
$customer_reschedule_horizon_response = parisan_core_rest_customer_reschedule_booking( $customer_reschedule_horizon );
parisan_public_enabled_assert( 400 === $customer_reschedule_horizon_response->get_status(), 'customer reschedule route enforces the owner-configured 30-day booking horizon' );
update_option( 'parisan_booking_horizon_days', 90 );

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
