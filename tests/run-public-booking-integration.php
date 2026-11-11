<?php
/**
 * Disposable WordPress/MariaDB integration checks for customer booking access.
 *
 * Run with: wp eval-file tests/run-public-booking-integration.php
 *
 * @package ParisanCore
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

function parisan_public_integration_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

parisan_public_integration_assert( false === parisan_core_public_booking_enabled(), 'public routes remain disabled unless the owner explicitly defines the feature constant' );
$disabled_shortcode = parisan_core_booking_shortcode();
parisan_public_integration_assert( false !== strpos( $disabled_shortcode, 'رزرو آنلاین در حال حاضر فعال نیست' ) && false === strpos( $disabled_shortcode, 'data-parisan-create-form' ), 'booking shortcode does not render a public form while disabled' );
do_action( 'rest_api_init', rest_get_server() );
$routes = rest_get_server()->get_routes();
parisan_public_integration_assert( ! isset( $routes['/parisan/v1/bookings'] ), 'public booking route is not registered by default' );
parisan_public_integration_assert( ! isset( $routes['/parisan/v1/customer-booking'] ), 'customer self-service route is not registered by default' );
parisan_public_integration_assert( ! isset( $routes['/parisan/v1/customer-booking/reschedule'] ) && ! isset( $routes['/parisan/v1/customer-booking/availability'] ), 'customer rescheduling routes are not registered by default' );

$tokens_table = $wpdb->prefix . 'parisan_customer_tokens';
$bookings_table = $wpdb->prefix . 'parisan_bookings';
$events_table = $wpdb->prefix . 'parisan_booking_events';
$wpdb->query( "DROP TABLE IF EXISTS {$tokens_table}" );
update_option( 'parisan_core_db_version', 2, false );
parisan_core_install_database();
$schema = (int) get_option( 'parisan_core_db_version', 0 );
parisan_public_integration_assert( 4 === $schema, 'schema v2 upgrades directly to schema v4' );
parisan_public_integration_assert( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tokens_table ) ) === $tokens_table, 'customer token table is created by the v2-to-v4 upgrade' );
$resource_column = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$bookings_table} LIKE %s", 'resource_id' ), ARRAY_A );
parisan_public_integration_assert( is_array( $resource_column ) && '0' === (string) $resource_column['Default'], 'v4 migration adds a backward-compatible resource assignment column' );

$now = current_time( 'mysql', true );
$booking_inserted = $wpdb->insert(
	$bookings_table,
	array(
		'public_id' => wp_generate_uuid4(),
		'service_id' => 0,
		'staff_id' => 1,
		'customer_user_id' => null,
		'customer_name' => 'Synthetic Token Customer',
		'customer_phone' => '09123456789',
		'customer_email' => 'token@example.test',
		'starts_at_utc' => gmdate( 'Y-m-d H:i:s', time() + 3600 ),
		'ends_at_utc' => gmdate( 'Y-m-d H:i:s', time() + 6300 ),
		'duration_minutes' => 45,
		'salon_timezone' => 'UTC',
		'status' => 'pending',
		'price_amount' => 150000,
		'currency_code' => 'IRR',
		'idempotency_key' => hash( 'sha256', 'public-token-integration-booking' ),
		'created_at_utc' => $now,
		'updated_at_utc' => $now,
	)
);
parisan_public_integration_assert( false !== $booking_inserted, 'synthetic booking inserted' );
$booking_id = (int) $wpdb->insert_id;

$token_one = bin2hex( random_bytes( 32 ) );
parisan_public_integration_assert( false !== $wpdb->query( 'START TRANSACTION' ), 'token transaction started' );
parisan_public_integration_assert( true === parisan_core_store_customer_access_token( $booking_id, $token_one ), 'first scoped customer token stored' );
parisan_public_integration_assert( false !== $wpdb->query( 'COMMIT' ), 'first token transaction committed' );

$stored_hash = $wpdb->get_var( $wpdb->prepare( "SELECT token_hash FROM {$tokens_table} WHERE booking_id = %d AND revoked_at_utc IS NULL", $booking_id ) );
parisan_public_integration_assert( is_string( $stored_hash ) && hash_equals( parisan_core_customer_token_hash( $token_one ), $stored_hash ), 'only the keyed token digest is stored' );
parisan_public_integration_assert( $token_one !== $stored_hash, 'raw customer token is not stored' );
$resolved = parisan_core_resolve_customer_access_token( $token_one );
parisan_public_integration_assert( is_array( $resolved ) && (int) $resolved['id'] === $booking_id, 'valid token resolves only its scoped booking' );
parisan_public_integration_assert( ! isset( $resolved['customer_phone'] ) && ! isset( $resolved['customer_email'] ), 'token resolver does not select customer contact fields' );

$token_two = bin2hex( random_bytes( 32 ) );
parisan_public_integration_assert( false !== $wpdb->query( 'START TRANSACTION' ), 'rotation transaction started' );
parisan_public_integration_assert( true === parisan_core_store_customer_access_token( $booking_id, $token_two ), 'replacement customer token stored' );
parisan_public_integration_assert( false !== $wpdb->query( 'COMMIT' ), 'rotation transaction committed' );
parisan_public_integration_assert( false === parisan_core_resolve_customer_access_token( $token_one ), 'rotated token is rejected' );
parisan_public_integration_assert( is_array( parisan_core_resolve_customer_access_token( $token_two ) ), 'replacement token is accepted' );

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$customer_request = new WP_REST_Request( 'GET', '/parisan/v1/customer-booking' );
$customer_request->set_header( 'X-Parisan-Booking-Token', $token_two );
$customer_response = parisan_core_rest_customer_booking( $customer_request );
parisan_public_integration_assert( $customer_response instanceof WP_REST_Response && 200 === $customer_response->get_status(), 'customer view accepts the scoped token' );
$customer_view = $customer_response->get_data();
parisan_public_integration_assert( ! isset( $customer_view['customer_name'] ) && ! isset( $customer_view['customer_phone'] ) && ! isset( $customer_view['customer_email'] ) && ! isset( $customer_view['public_id'] ), 'customer view omits direct identifiers and public references' );

$reference_request = new WP_REST_Request( 'GET', '/parisan/v1/customer-booking' );
$reference_request->set_param( 'public_id', 'not-an-authorization-token' );
$reference_response = parisan_core_rest_customer_booking( $reference_request );
parisan_public_integration_assert( 404 === $reference_response->get_status(), 'public reference/query parameters do not authorize customer access' );

$origin_request = new WP_REST_Request( 'POST', '/parisan/v1/bookings' );
$origin_request->set_header( 'Origin', home_url( '/' ) );
parisan_public_integration_assert( true === parisan_core_public_booking_same_origin( $origin_request ), 'configured same-origin requests are accepted' );
$origin_request->set_header( 'Origin', 'https://attacker.invalid' );
parisan_public_integration_assert( false === parisan_core_public_booking_same_origin( $origin_request ), 'cross-origin state-changing requests are rejected' );

$token_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tokens_table} WHERE token_hash = %s", parisan_core_customer_token_hash( $token_two ) ) );
$expired = gmdate( 'Y-m-d H:i:s', time() - 60 );
$wpdb->update( $tokens_table, array( 'expires_at_utc' => $expired ), array( 'id' => $token_id ), array( '%s' ), array( '%d' ) );
parisan_public_integration_assert( false === parisan_core_resolve_customer_access_token( $token_two ), 'expired customer token is rejected' );

$token_three = bin2hex( random_bytes( 32 ) );
parisan_public_integration_assert( false !== $wpdb->query( 'START TRANSACTION' ), 'cancellation token transaction started' );
parisan_public_integration_assert( true === parisan_core_store_customer_access_token( $booking_id, $token_three ), 'cancellation token stored' );
parisan_public_integration_assert( false !== $wpdb->query( 'COMMIT' ), 'cancellation token transaction committed' );
parisan_public_integration_assert( true === parisan_core_cancel_booking_with_customer_token( $booking_id, $token_three ), 'scoped customer cancellation succeeds' );
$cancelled_status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$bookings_table} WHERE id = %d", $booking_id ) );
$cancel_event = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$events_table} WHERE booking_id = %d AND event_type = 'customer_cancelled' AND to_status = 'cancelled'", $booking_id ) );
parisan_public_integration_assert( 'cancelled' === $cancelled_status && 1 === $cancel_event, 'cancellation and audit event persist together' );
$second_cancel = parisan_core_cancel_booking_with_customer_token( $booking_id, $token_three );
parisan_public_integration_assert( is_wp_error( $second_cancel ), 'already-cancelled booking cannot be cancelled again' );

echo "PASS: public booking gate, token hashing/rotation/expiry, scoped resolution, and customer cancellation integration.\n";
