<?php
/**
 * Disposable WordPress/MariaDB integration checks for date-specific schedules.
 *
 * Run with: wp eval-file tests/run-calendar-integration.php
 *
 * @package ParisanCore
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

function parisan_calendar_integration_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

parisan_calendar_integration_assert( 12 === (int) get_option( 'parisan_core_db_version', 0 ), 'schema v12 is installed' );
$overrides_table = $wpdb->prefix . 'parisan_schedule_date_overrides';
parisan_calendar_integration_assert( $overrides_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $overrides_table ) ), 'date override table exists' );
$unique_index = $wpdb->get_row( $wpdb->prepare( "SHOW INDEX FROM {$overrides_table} WHERE Key_name = %s", 'staff_date' ), ARRAY_A );
parisan_calendar_integration_assert( is_array( $unique_index ) && 0 === (int) $unique_index['Non_unique'], 'date override table enforces one row per staff and local date' );
parisan_calendar_integration_assert( function_exists( 'parisan_core_save_date_override' ) && function_exists( 'parisan_core_get_staff_effective_day_schedule' ) && function_exists( 'parisan_core_resolve_effective_day_schedule' ), 'calendar override services are loaded' );

update_option( 'timezone_string', 'Asia/Tehran' );
update_option( 'parisan_booking_lead_time_minutes', 0 );
update_option( 'parisan_booking_horizon_days', 90 );

$service_id = wp_insert_post(
	array(
		'post_type'   => 'parisan_service',
		'post_status' => 'publish',
		'post_title'  => 'Synthetic Calendar Service',
	),
	true
);
parisan_calendar_integration_assert( ! is_wp_error( $service_id ), 'synthetic calendar service is created' );
update_post_meta( $service_id, '_parisan_duration', 45 );
update_post_meta( $service_id, '_parisan_price', 150000 );
update_post_meta( $service_id, '_parisan_price_type', 'fixed' );

$staff_id = wp_insert_post(
	array(
		'post_type'   => 'parisan_staff',
		'post_status' => 'publish',
		'post_title'  => 'Synthetic Calendar Specialist',
	),
	true
);
parisan_calendar_integration_assert( ! is_wp_error( $staff_id ), 'synthetic calendar specialist is created' );
update_post_meta( $staff_id, '_parisan_staff_services', array( (int) $service_id ) );
update_post_meta( $staff_id, '_parisan_staff_weekly_schedule', array( 1 => array( 'start' => '09:00', 'end' => '17:00' ) ) );

$monday = current_datetime()->modify( 'next monday' );
$monday_date = $monday->format( 'Y-m-d' );
$sunday_date = $monday->modify( '-1 day' )->format( 'Y-m-d' );

$base_schedule = parisan_core_get_staff_effective_day_schedule( (int) $staff_id, $monday_date );
parisan_calendar_integration_assert( empty( $base_schedule['off'] ) && 'weekly' === $base_schedule['source'], 'weekly rule is the effective baseline schedule' );
$base_slots = parisan_core_get_available_slots( (int) $staff_id, (int) $service_id, $monday_date );
parisan_calendar_integration_assert( is_array( $base_slots ) && ! empty( $base_slots ), 'weekly schedule produces bookable slots before any override' );

$closed_sunday_schedule = parisan_core_get_staff_effective_day_schedule( (int) $staff_id, $sunday_date );
parisan_calendar_integration_assert( ! empty( $closed_sunday_schedule['off'] ) && 'weekly_closed' === $closed_sunday_schedule['source'], 'the weekly closed day is resolved as closed' );

$holiday = parisan_core_save_date_override(
	array(
		'staff_id'      => 0,
		'override_date' => $monday_date,
		'override_type' => 'holiday',
		'is_closed'     => true,
		'note'          => 'Synthetic salon holiday',
	)
);
parisan_calendar_integration_assert( is_array( $holiday ) && ! empty( $holiday['id'] ), 'salon holiday override is saved under the calendar lock' );
$holiday_schedule = parisan_core_get_staff_effective_day_schedule( (int) $staff_id, $monday_date );
parisan_calendar_integration_assert( ! empty( $holiday_schedule['off'] ) && 'salon_closure' === $holiday_schedule['source'], 'salon holiday overrides an open weekly rule for every specialist' );
$holiday_slots = parisan_core_get_available_slots( (int) $staff_id, (int) $service_id, $monday_date );
parisan_calendar_integration_assert( is_array( $holiday_slots ) && empty( $holiday_slots ), 'salon holiday removes all availability for the date' );

$holiday_input = array(
	'staff_id'             => (int) $staff_id,
	'service_id'           => (int) $service_id,
	'starts_at_local'      => $monday_date . ' 10:00',
	'customer_name'        => 'Synthetic Calendar Customer',
	'customer_phone'       => '09123456789',
	'customer_email'       => 'calendar@example.test',
	'customer_email_consent' => false,
	'idempotency_token'    => hash( 'sha256', 'calendar-holiday-rejection' ),
	'customer_access_token' => '',
);
$holiday_booking = parisan_core_create_booking( $holiday_input );
parisan_calendar_integration_assert( is_wp_error( $holiday_booking ) && 'parisan_booking_slot_unavailable' === $holiday_booking->get_error_code(), 'transactional booking creation rejects a salon holiday' );
parisan_calendar_integration_assert( true === parisan_core_delete_date_override( (int) $holiday['id'] ), 'salon holiday can be removed without deleting bookings' );

$staff_closure = parisan_core_save_date_override(
	array(
		'staff_id'      => (int) $staff_id,
		'override_date' => $monday_date,
		'override_type' => 'closure',
		'is_closed'     => true,
		'note'          => 'Synthetic specialist leave',
	)
);
parisan_calendar_integration_assert( is_array( $staff_closure ) && ! empty( $staff_closure['id'] ), 'specialist closure override is saved' );
$closure_schedule = parisan_core_get_staff_effective_day_schedule( (int) $staff_id, $monday_date );
parisan_calendar_integration_assert( ! empty( $closure_schedule['off'] ) && 'staff_closure' === $closure_schedule['source'], 'specialist closure wins over the open weekly rule' );
$closure_slots = parisan_core_get_available_slots( (int) $staff_id, (int) $service_id, $monday_date );
parisan_calendar_integration_assert( is_array( $closure_slots ) && empty( $closure_slots ), 'specialist closure removes their availability' );
parisan_calendar_integration_assert( true === parisan_core_delete_date_override( (int) $staff_closure['id'] ), 'specialist closure can be removed' );

$special_day = parisan_core_save_date_override(
	array(
		'staff_id'      => 0,
		'override_date' => $sunday_date,
		'override_type' => 'special_day',
		'start'         => '10:00',
		'end'           => '13:00',
		'break_start'   => '11:30',
		'break_end'     => '12:00',
		'note'          => 'Synthetic special working day',
	)
);
parisan_calendar_integration_assert( is_array( $special_day ) && ! empty( $special_day['id'] ), 'salon special working day is saved' );
$special_schedule = parisan_core_get_staff_effective_day_schedule( (int) $staff_id, $sunday_date );
parisan_calendar_integration_assert( empty( $special_schedule['off'] ) && 'salon_special_day' === $special_schedule['source'] && '10:00' === $special_schedule['start'], 'salon special day opens a weekly closed date with custom hours' );
$special_slots = parisan_core_get_available_slots( (int) $staff_id, (int) $service_id, $sunday_date );
parisan_calendar_integration_assert( is_array( $special_slots ) && ! empty( $special_slots ), 'special working day exposes custom-hours slots' );
foreach ( $special_slots as $slot ) {
	$clock = substr( $slot['starts_at_local'], 11 );
	parisan_calendar_integration_assert( $clock >= '10:00' && $clock <= '12:15', 'special-day slot stays inside custom hours and respects the break' );
}
parisan_calendar_integration_assert( true === parisan_core_delete_date_override( (int) $special_day['id'] ), 'special working day can be removed' );

$range_overrides = parisan_core_get_date_overrides_for_range( $sunday_date, $monday_date );
parisan_calendar_integration_assert( is_array( $range_overrides ) && 0 === count( $range_overrides ), 'deleted overrides no longer appear in the range query' );
parisan_calendar_integration_assert( false === parisan_core_save_date_override( array( 'override_date' => '2026-02-30', 'override_type' => 'holiday' ) ), 'invalid override dates fail closed' );

$lock_name = parisan_core_acquire_salon_calendar_lock();
parisan_calendar_integration_assert( is_string( $lock_name ) && '' !== $lock_name, 'salon calendar lock is acquired for booking/calendar serialization' );
$second_connection = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$second_connection->suppress_errors( true );
$blocked = $second_connection->get_var( $second_connection->prepare( 'SELECT GET_LOCK(%s, 0)', $lock_name ) );
parisan_calendar_integration_assert( '0' === (string) $blocked, 'an independent connection cannot acquire the held salon calendar lock' );
parisan_core_release_salon_calendar_lock( $lock_name );
$acquired_after_release = $second_connection->get_var( $second_connection->prepare( 'SELECT GET_LOCK(%s, 0)', $lock_name ) );
parisan_calendar_integration_assert( '1' === (string) $acquired_after_release, 'the salon calendar lock becomes available after release' );
$second_connection->get_var( $second_connection->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );

$booking_input = array(
	'staff_id'             => (int) $staff_id,
	'service_id'           => (int) $service_id,
	'starts_at_local'      => $base_slots[0]['starts_at_local'],
	'customer_name'        => 'Synthetic Calendar Customer',
	'customer_phone'       => '09123456789',
	'customer_email'       => 'calendar@example.test',
	'customer_email_consent' => false,
	'idempotency_token'    => hash( 'sha256', 'calendar-created-booking' ),
	'customer_access_token' => '',
);
$calendar_booking = parisan_core_create_booking( $booking_input );
parisan_calendar_integration_assert( ! is_wp_error( $calendar_booking ) && ! empty( $calendar_booking['booking_id'] ), 'a booking can be created after the holiday and closure are removed' );
$day_summary = parisan_core_get_day_booking_summary( $monday_date, array( (int) $staff_id ) );
parisan_calendar_integration_assert( is_array( $day_summary ) && (int) $day_summary['total'] >= 1 && (int) $day_summary['by_staff'][ (int) $staff_id ] >= 1, 'day booking summary reports the synthetic booking for its specialist' );

$wpdb->delete( $wpdb->prefix . 'parisan_booking_events', array( 'booking_id' => (int) $calendar_booking['booking_id'] ), array( '%d' ) );
$wpdb->delete( $wpdb->prefix . 'parisan_bookings', array( 'id' => (int) $calendar_booking['booking_id'] ), array( '%d' ) );
wp_delete_post( (int) $staff_id, true );
wp_delete_post( (int) $service_id, true );

echo "PASS: date-specific calendar overrides, transactional booking rejection, and salon lock serialization.\n";
