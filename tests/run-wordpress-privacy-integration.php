<?php
/**
 * Real WordPress/MariaDB privacy lifecycle integration checks.
 *
 * Run with: wp eval-file tests/run-wordpress-privacy-integration.php
 *
 * @package ParisanCore
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

function parisan_privacy_integration_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

function parisan_privacy_integration_insert_booking( $email, $status, $token, $updated_at ) {
	global $wpdb;
	$table = $wpdb->prefix . 'parisan_bookings';
	$inserted = $wpdb->insert(
		$table,
		array(
			'public_id'         => wp_generate_uuid4(),
			'service_id'        => 0,
			'staff_id'          => 0,
			'customer_user_id'  => null,
			'customer_name'     => 'Synthetic Privacy Customer',
			'customer_phone'    => '09123456789',
			'customer_email'    => $email,
			'starts_at_utc'     => '2026-11-09 09:00:00',
			'ends_at_utc'       => '2026-11-09 09:45:00',
			'duration_minutes'  => 45,
			'salon_timezone'    => 'Asia/Tehran',
			'status'            => $status,
			'price_amount'      => 120000,
			'currency_code'     => 'IRR',
			'idempotency_key'   => hash( 'sha256', $token ),
			'created_at_utc'    => '2026-11-01 00:00:00',
			'updated_at_utc'    => $updated_at,
		)
	);
	if ( false === $inserted ) {
		fwrite( STDERR, 'Could not insert synthetic booking: ' . $wpdb->last_error . "\n" );
		exit( 1 );
	}
	$booking_id = (int) $wpdb->insert_id;
	$event_inserted = $wpdb->insert(
		$wpdb->prefix . 'parisan_booking_events',
		array(
			'booking_id'     => $booking_id,
			'actor_user_id'  => null,
			'event_type'     => 'created',
			'from_status'    => '',
			'to_status'      => 'pending',
			'event_note'     => 'Synthetic private event note',
			'created_at_utc' => '2026-11-01 00:00:00',
		)
	);
	if ( false === $event_inserted ) {
		fwrite( STDERR, 'Could not insert synthetic booking event: ' . $wpdb->last_error . "\n" );
		exit( 1 );
	}
	return $booking_id;
}

$terminal_id = parisan_privacy_integration_insert_booking( 'privacy@example.test', 'completed', 'privacy-terminal-0001', '2026-11-01 00:00:00' );
$active_id = parisan_privacy_integration_insert_booking( 'privacy@example.test', 'pending', 'privacy-active-0002', '2026-11-01 00:00:00' );

$export = parisan_core_privacy_exporter_callback( 'privacy@example.test', 1 );
parisan_privacy_integration_assert( 2 === count( $export['data'] ), 'WordPress exporter returns both matching bookings' );
$export_values = array();
foreach ( $export['data'] as $item ) {
	foreach ( $item['data'] as $field ) {
		$export_values[] = $field['value'];
	}
}
parisan_privacy_integration_assert( in_array( 'Synthetic Privacy Customer', $export_values, true ), 'export includes customer identity' );
parisan_privacy_integration_assert( false !== strpos( implode( ' ', $export_values ), 'Synthetic private event note' ), 'export includes booking event history' );

$erasure = parisan_core_privacy_eraser_callback( 'privacy@example.test', 1 );
parisan_privacy_integration_assert( 1 === $erasure['items_removed'], 'eraser anonymizes the terminal booking' );
parisan_privacy_integration_assert( 1 === $erasure['items_retained'], 'eraser reports the active booking retained' );
$terminal = $wpdb->get_row( $wpdb->prepare( "SELECT customer_name, customer_phone, customer_email, customer_user_id FROM {$wpdb->prefix}parisan_bookings WHERE id = %d", $terminal_id ), ARRAY_A );
$active = $wpdb->get_row( $wpdb->prepare( "SELECT customer_name, customer_phone FROM {$wpdb->prefix}parisan_bookings WHERE id = %d", $active_id ), ARRAY_A );
$terminal_note = $wpdb->get_var( $wpdb->prepare( "SELECT event_note FROM {$wpdb->prefix}parisan_booking_events WHERE booking_id = %d", $terminal_id ) );
$terminal_events = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}parisan_booking_events WHERE booking_id = %d", $terminal_id ) );
parisan_privacy_integration_assert( 'اطلاعات حذف‌شده' === $terminal['customer_name'] && '' === $terminal['customer_phone'] && '' === $terminal['customer_email'] && null === $terminal['customer_user_id'], 'terminal booking contact fields are anonymized' );
parisan_privacy_integration_assert( null === $terminal_note && 1 === $terminal_events, 'event note is cleared while audit event remains' );
parisan_privacy_integration_assert( 'Synthetic Privacy Customer' === $active['customer_name'] && '09123456789' === $active['customer_phone'], 'active booking contact data is preserved' );

$rollback_id = parisan_privacy_integration_insert_booking( 'rollback@example.test', 'completed', 'privacy-rollback-0003', '2026-11-01 00:00:00' );
$events_table = $wpdb->prefix . 'parisan_booking_events';
$trigger = 'parisan_privacy_force_event_update_failure';
$created_trigger = $wpdb->query( "CREATE TRIGGER {$trigger} BEFORE UPDATE ON {$events_table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced_privacy_rollback'" );
parisan_privacy_integration_assert( false !== $created_trigger, 'rollback test trigger is created' );
try {
	$rollback_result = parisan_core_privacy_eraser_callback( 'rollback@example.test', 1 );
} finally {
	$wpdb->query( "DROP TRIGGER IF EXISTS {$trigger}" );
}
$rollback_row = $wpdb->get_row( $wpdb->prepare( "SELECT customer_name, customer_phone, customer_email FROM {$wpdb->prefix}parisan_bookings WHERE id = %d", $rollback_id ), ARRAY_A );
parisan_privacy_integration_assert( false === $rollback_result['items_removed'], 'eraser reports forced audit-note failure' );
parisan_privacy_integration_assert( 'Synthetic Privacy Customer' === $rollback_row['customer_name'] && '09123456789' === $rollback_row['customer_phone'] && 'rollback@example.test' === $rollback_row['customer_email'], 'failed erasure rolls booking PII update back' );

$retention_id = parisan_privacy_integration_insert_booking( 'retention@example.test', 'completed', 'privacy-retention-0004', '2025-01-01 00:00:00' );
update_option( PARISAN_CORE_RETENTION_OPTION, 90, false );
$retention_result = parisan_core_run_booking_retention_cleanup();
$retention_row = $wpdb->get_row( $wpdb->prepare( "SELECT customer_name, customer_phone, customer_email FROM {$wpdb->prefix}parisan_bookings WHERE id = %d", $retention_id ), ARRAY_A );
parisan_privacy_integration_assert( 1 === $retention_result, 'opt-in retention cleanup anonymizes one old terminal booking' );
parisan_privacy_integration_assert( 'اطلاعات حذف‌شده' === $retention_row['customer_name'] && '' === $retention_row['customer_phone'] && '' === $retention_row['customer_email'], 'retention cleanup removes direct customer identifiers' );

$_SERVER['REMOTE_ADDR'] = '192.0.2.77';
parisan_privacy_integration_assert( true === parisan_core_check_rate_limit( 'integration_test', 1, 300 ), 'first rate-limited request is accepted' );
$rate_limited = parisan_core_check_rate_limit( 'integration_test', 1, 300 );
parisan_privacy_integration_assert( is_wp_error( $rate_limited ) && 'parisan_rate_limit_exceeded' === $rate_limited->get_error_code(), 'second request is rejected with rate limit' );

echo "PASS: WordPress/MariaDB booking privacy export, erasure, transaction rollback, retention, and rate-limit integration.\n";
