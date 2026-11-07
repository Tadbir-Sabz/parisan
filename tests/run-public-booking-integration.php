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
do_action( 'rest_api_init', rest_get_server() );
$routes = rest_get_server()->get_routes();
parisan_public_integration_assert( ! isset( $routes['/parisan/v1/bookings'] ), 'public booking route is not registered by default' );
parisan_public_integration_assert( ! isset( $routes['/parisan/v1/customer-booking'] ), 'customer self-service route is not registered by default' );

$tokens_table = $wpdb->prefix . 'parisan_customer_tokens';
$bookings_table = $wpdb->prefix . 'parisan_bookings';
$events_table = $wpdb->prefix . 'parisan_booking_events';
$schema = (int) get_option( 'parisan_core_db_version', 0 );
parisan_public_integration_assert( 3 === $schema, 'database schema v3 is installed' );
parisan_public_integration_assert( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tokens_table ) ) === $tokens_table, 'customer token table exists' );

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

$token_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tokens_table} WHERE token_hash = %s", parisan_core_customer_token_hash( $token_two ) ) );
$expired = gmdate( 'Y-m-d H:i:s', time() - 60 );
$wpdb->update( $tokens_table, array( 'expires_at_utc' => $expired ), array( 'id' => $token_id ), array( '%s' ), array( '%d' ) );
parisan_public_integration_assert( false === parisan_core_resolve_customer_access_token( $token_two ), 'expired customer token is rejected' );

$token_three = bin2hex( random_bytes( 32 ) );
parisan_public_integration_assert( 1 === $wpdb->query( 'START TRANSACTION' ), 'cancellation token transaction started' );
parisan_public_integration_assert( true === parisan_core_store_customer_access_token( $booking_id, $token_three ), 'cancellation token stored' );
parisan_public_integration_assert( 1 === $wpdb->query( 'COMMIT' ), 'cancellation token transaction committed' );
parisan_public_integration_assert( true === parisan_core_cancel_booking_with_customer_token( $booking_id, $token_three ), 'scoped customer cancellation succeeds' );
$cancelled_status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$bookings_table} WHERE id = %d", $booking_id ) );
$cancel_event = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$events_table} WHERE booking_id = %d AND event_type = 'customer_cancelled' AND to_status = 'cancelled'", $booking_id ) );
parisan_public_integration_assert( 'cancelled' === $cancelled_status && 1 === $cancel_event, 'cancellation and audit event persist together' );
$second_cancel = parisan_core_cancel_booking_with_customer_token( $booking_id );
parisan_public_integration_assert( is_wp_error( $second_cancel ), 'already-cancelled booking cannot be cancelled again' );

echo "PASS: public booking gate, token hashing/rotation/expiry, scoped resolution, and customer cancellation integration.\n";
