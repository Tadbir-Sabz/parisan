<?php
/**
 * Focused tests for Parisan privacy policy helpers and request throttling.
 *
 * @package ParisanCore
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'ARRAY_A', 'ARRAY_A' );

$GLOBALS['parisan_test_hooks'] = array();
$GLOBALS['parisan_test_options'] = array();
$GLOBALS['parisan_test_transients'] = array();
$GLOBALS['parisan_test_scheduled'] = array();

class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code = '', $message = '', $data = null ) {
		$this->code = $code;
		$this->message = $message;
		$this->data = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

class ParisanPrivacyFakeWpdb {
	public $dbname = 'parisan_test';
	public $prefix = 'wp_';
	public $queries = array();
	public $lock_result = '1';
	public $fail_query = false;
	public function prepare( $query, $args ) {
		if ( ! is_array( $args ) ) {
			$args = func_get_args();
			array_shift( $args );
		}
		foreach ( $args as $arg ) {
			$replacement = is_int( $arg ) ? (string) $arg : "'" . addslashes( (string) $arg ) . "'";
			$query = preg_replace( '/%[sd]/', $replacement, $query, 1 );
		}
		return $query;
	}
	public function esc_like( $value ) { return addcslashes( $value, '_%\\' ); }
	public function get_row( $query, $format = null ) {
		return array( 'Engine' => 'InnoDB' );
	}
	public function get_var( $query ) {
		if ( false !== strpos( $query, 'GET_LOCK' ) ) { return $this->lock_result; }
		if ( false !== strpos( $query, 'RELEASE_LOCK' ) ) { return 1; }
		if ( false !== strpos( $query, 'COUNT(*)' ) ) { return 2; }
		return null;
	}
	public function query( $query ) {
		$this->queries[] = $query;
		return $this->fail_query ? false : 1;
	}
	public function get_results( $query, $format = null ) {
		if ( false !== strpos( $query, 'SELECT id, public_id' ) ) {
			return array(
				array(
					'id' => 7, 'public_id' => 'test-public-id', 'service_id' => 11, 'staff_id' => 12,
					'customer_name' => 'Synthetic Customer', 'customer_phone' => '09120000000',
					'customer_email' => 'customer@example.test', 'starts_at_utc' => '2026-11-09 09:00:00',
					'ends_at_utc' => '2026-11-09 09:45:00', 'duration_minutes' => 45,
					'salon_timezone' => 'Asia/Tehran', 'status' => 'completed', 'price_amount' => '120000',
					'currency_code' => 'IRR', 'created_at_utc' => '2026-11-01 00:00:00',
					'updated_at_utc' => '2026-11-09 10:00:00',
				),
			);
		}
		if ( false !== strpos( $query, 'SELECT event_type' ) ) {
			return array(
				array(
					'event_type' => 'created', 'from_status' => '', 'to_status' => 'pending',
					'event_note' => 'Synthetic note', 'created_at_utc' => '2026-11-01 00:00:00',
				),
			);
		}
		if ( false !== strpos( $query, 'SELECT id FROM' ) ) {
			return array( array( 'id' => 7 ) );
		}
		return array();
	}
}

$wpdb = new ParisanPrivacyFakeWpdb();

function add_filter( $hook, $callback ) { $GLOBALS['parisan_test_hooks'][ $hook ][] = $callback; }
function add_action( $hook, $callback ) { $GLOBALS['parisan_test_hooks'][ $hook ][] = $callback; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_email( $value ) { return filter_var( $value, FILTER_SANITIZE_EMAIL ); }
function is_email( $value ) { return (bool) filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ); }
function __( $value, $domain = null ) { return $value; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_salt( $scheme = 'auth' ) { return 'test-only-salt'; }
function get_transient( $key ) { return isset( $GLOBALS['parisan_test_transients'][ $key ] ) ? $GLOBALS['parisan_test_transients'][ $key ] : false; }
function set_transient( $key, $value, $expiration = 0 ) { $GLOBALS['parisan_test_transients'][ $key ] = $value; return true; }
function get_option( $key, $default = false ) { return isset( $GLOBALS['parisan_test_options'][ $key ] ) ? $GLOBALS['parisan_test_options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['parisan_test_options'][ $key ] = $value; return true; }
function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['parisan_test_scheduled'][ $hook ] ); }
function wp_schedule_event( $timestamp, $recurrence, $hook ) { $GLOBALS['parisan_test_scheduled'][ $hook ] = array( $timestamp, $recurrence ); }
function wp_list_pluck( $list, $field ) { return array_map( static function ( $item ) use ( $field ) { return is_object( $item ) ? $item->$field : $item[ $field ]; }, $list ); }
function get_the_title( $id ) { return 11 === $id ? 'Synthetic Service' : 'Synthetic Specialist'; }
function get_user_by( $field, $value ) { return false; }
function current_time( $type, $gmt = false ) { return '2026-11-07 00:00:00'; }
function wp_kses_post( $value ) { return $value; }
function wp_add_privacy_policy_content( $name, $content ) {}
function wp_die( $message, $status = 0 ) { throw new RuntimeException( $message ); }

require dirname( __DIR__ ) . '/plugin/parisan-core/includes/privacy.php';

$assertions = 0;
function assert_true( $condition, $message ) {
	global $assertions;
	$assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$identity = parisan_core_privacy_identity_predicate( 'customer@example.test', 42 );
assert_true( false !== strpos( $identity['sql'], 'customer_email = %s' ), 'privacy identity includes email predicate' );
assert_true( false !== strpos( $identity['sql'], 'customer_user_id = %d' ), 'privacy identity includes user ID predicate' );
assert_true( 2 === count( $identity['args'] ), 'privacy identity binds both values' );
assert_true( '1 = 0' === parisan_core_privacy_identity_predicate( 'not-an-email', 0 )['sql'], 'invalid identity matches no records' );

$statuses = parisan_core_erasable_booking_statuses();
assert_true( in_array( 'completed', $statuses, true ) && in_array( 'cancelled', $statuses, true ), 'terminal bookings are eligible for anonymization' );
assert_true( ! in_array( 'pending', $statuses, true ) && ! in_array( 'confirmed', $statuses, true ), 'active bookings are retained' );

$export = parisan_core_export_booking_personal_data( 'customer@example.test', 0, 1 );
assert_true( 1 === count( $export ), 'booking privacy exporter returns the matching record' );
assert_true( 'parisan-bookings' === $export[0]['group_id'], 'booking export is grouped under Parisan bookings' );
assert_true( in_array( 'Synthetic Customer', array_column( $export[0]['data'], 'value' ), true ), 'booking export includes customer data' );
assert_true( false !== strpos( implode( ' ', array_column( $export[0]['data'], 'value' ) ), 'Synthetic note' ), 'booking export includes associated event notes' );

$eraser = parisan_core_privacy_eraser_callback( 'customer@example.test', 1 );
assert_true( 1 === $eraser['items_removed'], 'privacy eraser reports anonymized terminal booking' );
assert_true( 2 === $eraser['items_retained'], 'privacy eraser reports active bookings retained' );
assert_true( true === $eraser['done'], 'privacy eraser completes the request' );
$joined_queries = implode( "\n", $wpdb->queries );
assert_true( false !== strpos( $joined_queries, 'SET customer_name' ), 'eraser clears booking contact fields' );
assert_true( false !== strpos( $joined_queries, 'SET event_note = NULL' ), 'eraser clears free-form event notes' );
assert_true( false !== strpos( $joined_queries, 'COMMIT' ), 'eraser commits booking and event anonymization atomically' );

$_SERVER['REMOTE_ADDR'] = '192.0.2.10';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.99';
assert_true( true === parisan_core_check_rate_limit( 'public_booking', 2, 300 ), 'first request is accepted' );
assert_true( true === parisan_core_check_rate_limit( 'public_booking', 2, 300 ), 'request within configured limit is accepted' );
$limited = parisan_core_check_rate_limit( 'public_booking', 2, 300 );
assert_true( is_wp_error( $limited ) && 'parisan_rate_limit_exceeded' === $limited->get_error_code(), 'request above limit is rejected' );
assert_true( 429 === $limited->get_error_data()['status'], 'rate-limit response carries HTTP 429 metadata' );

$wpdb->lock_result = '0';
$unavailable = parisan_core_check_rate_limit( 'other_action', 2, 300 );
assert_true( is_wp_error( $unavailable ) && 'parisan_rate_limit_unavailable' === $unavailable->get_error_code(), 'rate limiter fails closed when lock is unavailable' );

$GLOBALS['parisan_test_options'][ PARISAN_CORE_RETENTION_OPTION ] = 0;
assert_true( 0 === parisan_core_run_booking_retention_cleanup(), 'automatic retention is disabled by default' );
parisan_core_update_retention_schedule( 0 );
assert_true( ! isset( $GLOBALS['parisan_test_scheduled'][ PARISAN_CORE_RETENTION_HOOK ] ), 'zero retention clears scheduled cleanup' );
parisan_core_update_retention_schedule( 365 );
assert_true( 'daily' === $GLOBALS['parisan_test_scheduled'][ PARISAN_CORE_RETENTION_HOOK ][1], 'opt-in retention schedules bounded daily cleanup' );

assert_true( isset( $GLOBALS['parisan_test_hooks']['wp_privacy_personal_data_exporters'] ), 'WordPress privacy exporter is registered' );
assert_true( isset( $GLOBALS['parisan_test_hooks']['wp_privacy_personal_data_erasers'] ), 'WordPress privacy eraser is registered' );

echo "PASS: {$assertions} Parisan privacy/rate-limit assertions.\n";
