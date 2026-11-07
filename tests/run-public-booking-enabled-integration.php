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
foreach ( array( '/parisan/v1/availability', '/parisan/v1/bookings', '/parisan/v1/customer-booking', '/parisan/v1/customer-booking/cancel' ) as $route ) {
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
parisan_public_enabled_assert( false !== strpos( $html, 'Synthetic Public Booking Service' ), 'published services are offered in the wizard' );
parisan_public_enabled_assert( false !== strpos( $html, 'Synthetic Public Booking Specialist' ), 'eligible specialists are offered in the wizard' );
parisan_public_enabled_assert( wp_script_is( 'parisan-public-booking', 'enqueued' ), 'public booking JavaScript is enqueued' );
parisan_public_enabled_assert( wp_style_is( 'parisan-public-booking', 'enqueued' ), 'public booking CSS is enqueued' );

echo "PASS: explicit public API enablement, route registration, and Persian booking shortcode rendering.\n";
