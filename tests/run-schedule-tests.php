<?php
/**
 * Standalone tests for schedule normalization and availability calculation.
 *
 * Run: php tests/run-schedule-tests.php
 */

require_once __DIR__ . '/../plugin/parisan-core/includes/schedule.php';

$passed = 0;
$failed = 0;

/**
 * Record a test assertion.
 *
 * @param bool   $condition Assertion result.
 * @param string $message Assertion description.
 * @return void
 */
function parisan_test_expect( $condition, $message ) {
	global $passed, $failed;

	if ( $condition ) {
		++$passed;
		echo "PASS: {$message}\n";
	} else {
		++$failed;
		fwrite( STDERR, "FAIL: {$message}\n" );
	}
}

$valid = parisan_core_normalize_weekly_schedule(
	array(
		6 => array( 'start' => '09:00', 'end' => '18:00' ),
		0 => array( 'off' => '1', 'start' => '09:00', 'end' => '18:00' ),
		1 => array( 'start' => '10:15', 'end' => '16:45' ),
	)
);

parisan_test_expect( array( 'off' => false, 'start' => '09:00', 'end' => '18:00' ) === $valid[6], 'accepts a valid Saturday time window' );
parisan_test_expect( array( 'off' => true ) === $valid[0], 'respects an explicitly closed day' );
parisan_test_expect( array( 'off' => false, 'start' => '10:15', 'end' => '16:45' ) === $valid[1], 'accepts zero-padded 24-hour times' );
parisan_test_expect( array( 'off' => true ) === $valid[2], 'missing day defaults to unavailable' );
parisan_test_expect( array( 'off' => true ) === parisan_core_normalize_weekly_schedule( array( 3 => array( 'start' => '9:00', 'end' => '18:00' ) ) )[3], 'rejects non-zero-padded time input' );
parisan_test_expect( array( 'off' => true ) === parisan_core_normalize_weekly_schedule( array( 3 => array( 'start' => '17:00', 'end' => '09:00' ) ) )[3], 'rejects end time earlier than start time' );
parisan_test_expect( array( 'off' => true ) === parisan_core_normalize_weekly_schedule( array( 3 => array( 'start' => '09:00', 'end' => '24:00' ) ) )[3], 'rejects invalid 24:00 clock value' );
parisan_test_expect( array( 'off' => true ) === parisan_core_normalize_weekly_schedule( array( 3 => array( 'start' => array( 'unexpected' ), 'end' => '18:00' ) ) )[3], 'rejects non-scalar time input without warnings' );
parisan_test_expect( 7 === count( parisan_core_normalize_weekly_schedule( 'invalid' ) ), 'returns all seven weekdays for malformed root input' );

$weekly = parisan_core_normalize_weekly_schedule( array( 1 => array( 'start' => '09:00', 'end' => '12:00' ) ) );
$slots = parisan_core_calculate_available_slots( '2026-11-09', $weekly, 'Europe/Berlin', 60, 30 );
parisan_test_expect( 5 === count( $slots ), 'generates expected slots using salon-local weekday hours' );
parisan_test_expect( '2026-11-09 09:00' === $slots[0]['starts_at_local'], 'returns salon-local display time' );
parisan_test_expect( '2026-11-09 08:00:00' === $slots[0]['starts_at_utc'], 'converts local start to UTC correctly' );
parisan_test_expect( '2026-11-09 09:00:00' === $slots[0]['ends_at_utc'], 'returns service end in UTC' );

$busy = array( array( 'start' => '2026-11-09 08:30:00', 'end' => '2026-11-09 09:30:00' ) );
$without_busy = parisan_core_calculate_available_slots( '2026-11-09', $weekly, 'Europe/Berlin', 60, 30, $busy );
parisan_test_expect( 3 === count( $without_busy ), 'excludes slots overlapping an existing booking' );
parisan_test_expect( '2026-11-09 10:00' === $without_busy[0]['starts_at_local'], 'first available slot follows the busy interval' );

$blocked = array( array( 'start' => '2026-11-09 09:00:00', 'end' => '2026-11-09 10:00:00' ) );
$without_exception = parisan_core_calculate_available_slots( '2026-11-09', $weekly, 'Europe/Berlin', 60, 30, array(), $blocked );
parisan_test_expect( 3 === count( $without_exception ), 'excludes slots overlapping date-specific unavailable intervals' );

$buffered = parisan_core_calculate_available_slots( '2026-11-09', $weekly, 'Europe/Berlin', 60, 30, array(), array(), 15, 15 );
parisan_test_expect( 3 === count( $buffered ), 'reserves before/after buffers and keeps them inside working hours' );
parisan_test_expect( '2026-11-09 09:30' === $buffered[0]['starts_at_local'], 'buffer prevents a slot from starting at opening time' );

$closed = parisan_core_calculate_available_slots( '2026-11-08', $weekly, 'Europe/Berlin', 60, 30 );
parisan_test_expect( 0 === count( $closed ), 'returns no slots for a closed weekday' );
$invalid_timezone = parisan_core_calculate_available_slots( '2026-11-09', $weekly, 'Not/A-Timezone', 60, 30 );
parisan_test_expect( array() === $invalid_timezone, 'fails closed for an invalid timezone' );
$invalid_date = parisan_core_calculate_available_slots( '2026-02-30', $weekly, 'Europe/Berlin', 60, 30 );
parisan_test_expect( array() === $invalid_date, 'rejects impossible calendar dates' );
$invalid_dst_time = parisan_core_local_datetime_to_utc( '2026-03-29', '02:30', new DateTimeZone( 'Europe/Berlin' ) );
parisan_test_expect( false === $invalid_dst_time, 'rejects a nonexistent local wall-clock time during DST spring-forward' );

echo "\nSchedule tests: {$passed} passed, {$failed} failed.\n";
exit( 0 === $failed ? 0 : 1 );
