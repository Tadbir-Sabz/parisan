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
$with_break = parisan_core_normalize_weekly_schedule( array( 1 => array( 'start' => '09:00', 'end' => '18:00', 'break_start' => '13:00', 'break_end' => '14:00' ) ) );
parisan_test_expect( array( 'off' => false, 'start' => '09:00', 'end' => '18:00', 'break_start' => '13:00', 'break_end' => '14:00' ) === $with_break[1], 'accepts a valid recurring break contained within working hours' );
parisan_test_expect( array( 'off' => true ) === parisan_core_normalize_weekly_schedule( array( 1 => array( 'start' => '09:00', 'end' => '18:00', 'break_start' => '13:00' ) ) )[1], 'fails closed when only one break boundary is provided' );
parisan_test_expect( array( 'off' => true ) === parisan_core_normalize_weekly_schedule( array( 1 => array( 'start' => '09:00', 'end' => '18:00', 'break_start' => '08:30', 'break_end' => '09:30' ) ) )[1], 'fails closed when a recurring break falls outside working hours' );
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
parisan_test_expect( 2 === count( $without_busy ), 'excludes slots overlapping an existing booking' );
parisan_test_expect( '2026-11-09 10:30' === $without_busy[0]['starts_at_local'], 'first available slot follows the busy interval' );

$blocked = array( array( 'start' => '2026-11-09 09:00:00', 'end' => '2026-11-09 10:00:00' ) );
$without_exception = parisan_core_calculate_available_slots( '2026-11-09', $weekly, 'Europe/Berlin', 60, 30, array(), $blocked );
parisan_test_expect( 2 === count( $without_exception ), 'excludes slots overlapping date-specific unavailable intervals' );

$buffered = parisan_core_calculate_available_slots( '2026-11-09', $weekly, 'Europe/Berlin', 60, 30, array(), array(), 15, 15 );
parisan_test_expect( 3 === count( $buffered ), 'reserves before/after buffers and keeps them inside working hours' );
parisan_test_expect( '2026-11-09 09:30' === $buffered[0]['starts_at_local'], 'buffer prevents a slot from starting at opening time' );
$break_slots = parisan_core_calculate_available_slots( '2026-11-09', $with_break, 'Europe/Berlin', 60, 30 );
$break_starts = array_column( $break_slots, 'starts_at_local' );
parisan_test_expect( in_array( '2026-11-09 12:00', $break_starts, true ) && ! in_array( '2026-11-09 12:30', $break_starts, true ) && ! in_array( '2026-11-09 13:00', $break_starts, true ) && ! in_array( '2026-11-09 13:30', $break_starts, true ), 'allows a service ending at break start and excludes every overlapping appointment' );
parisan_test_expect( in_array( '2026-11-09 14:00', $break_starts, true ) && ! in_array( '2026-11-09 13:00', $break_starts, true ), 'resumes availability after the recurring break ends' );

$closed = parisan_core_calculate_available_slots( '2026-11-08', $weekly, 'Europe/Berlin', 60, 30 );
parisan_test_expect( 0 === count( $closed ), 'returns no slots for a closed weekday' );
$invalid_timezone = parisan_core_calculate_available_slots( '2026-11-09', $weekly, 'Not/A-Timezone', 60, 30 );
parisan_test_expect( array() === $invalid_timezone, 'fails closed for an invalid timezone' );
$invalid_date = parisan_core_calculate_available_slots( '2026-02-30', $weekly, 'Europe/Berlin', 60, 30 );
parisan_test_expect( array() === $invalid_date, 'rejects impossible calendar dates' );
$invalid_dst_time = parisan_core_local_datetime_to_utc( '2026-03-29', '02:30', new DateTimeZone( 'Europe/Berlin' ) );
parisan_test_expect( false === $invalid_dst_time, 'rejects a nonexistent local wall-clock time during DST spring-forward' );

$partial = parisan_core_local_interval_to_utc( '2026-11-09', '11:00', '2026-11-09', '12:00', new DateTimeZone( 'Europe/Berlin' ) );
parisan_test_expect( is_array( $partial ) && '2026-11-09 10:00:00' === $partial['start']->format( 'Y-m-d H:i:s' ) && '2026-11-09 11:00:00' === $partial['end']->format( 'Y-m-d H:i:s' ), 'converts a partial-day exception to UTC' );
$all_day = parisan_core_local_interval_to_utc( '2026-11-09', '00:00', '2026-11-10', '00:00', new DateTimeZone( 'Europe/Berlin' ) );
parisan_test_expect( is_array( $all_day ) && 86400 === $all_day['end']->getTimestamp() - $all_day['start']->getTimestamp(), 'converts a normal full-day exception to a 24-hour UTC interval' );
parisan_test_expect( false === parisan_core_local_interval_to_utc( '2026-11-09', '13:00', '2026-11-09', '12:00', new DateTimeZone( 'Europe/Berlin' ) ), 'rejects an exception whose end precedes its start' );
parisan_test_expect( false === parisan_core_local_interval_to_utc( '2026-03-29', '02:30', '2026-03-29', '03:30', new DateTimeZone( 'Europe/Berlin' ) ), 'rejects a date exception crossing a nonexistent DST local time' );

$holiday_override = parisan_core_normalize_date_override(
	array(
		'staff_id'      => 0,
		'override_date' => '2026-11-09',
		'override_type' => 'holiday',
		'is_closed'     => true,
		'note'          => 'تعطیلی سالن',
	)
);
parisan_test_expect( is_array( $holiday_override ) && true === $holiday_override['is_closed'] && 'holiday' === $holiday_override['override_type'], 'normalizes a salon-wide holiday override' );
$special_override = parisan_core_normalize_date_override(
	array(
		'staff_id'      => 0,
		'override_date' => '2026-11-08',
		'override_type' => 'special_day',
		'start'         => '10:00',
		'end'           => '14:00',
		'break_start'   => '12:00',
		'break_end'     => '12:30',
	)
);
parisan_test_expect( is_array( $special_override ) && false === $special_override['is_closed'] && '10:00' === $special_override['start'] && '12:30' === $special_override['break_end'], 'normalizes a salon-wide special working day with a break' );
parisan_test_expect( false === parisan_core_normalize_date_override( array( 'override_date' => '2026-11-09', 'override_type' => 'custom_hours', 'start' => '18:00', 'end' => '09:00' ) ), 'rejects an override whose end precedes its start' );
parisan_test_expect( false === parisan_core_normalize_date_override( array( 'override_date' => '2026-02-30', 'override_type' => 'holiday' ) ), 'rejects an impossible override date' );
parisan_test_expect( false === parisan_core_normalize_date_override( array( 'override_date' => '2026-11-09', 'override_type' => 'custom_hours', 'start' => '09:00', 'end' => '18:00', 'break_start' => '19:00', 'break_end' => '20:00' ) ), 'rejects a break outside override working hours' );

$resolved_holiday = parisan_core_resolve_effective_day_schedule( '2026-11-09', $weekly, null, $holiday_override );
parisan_test_expect( ! empty( $resolved_holiday['off'] ) && 'salon_closure' === $resolved_holiday['source'], 'a salon holiday closes an otherwise working weekday' );
$holiday_slots = parisan_core_calculate_available_slots( '2026-11-09', $weekly, 'Europe/Berlin', 60, 30, array(), array(), 0, 0, null, $holiday_override );
parisan_test_expect( array() === $holiday_slots, 'holiday overrides produce no available slots' );

$staff_leave = parisan_core_normalize_date_override( array( 'staff_id' => 12, 'override_date' => '2026-11-09', 'override_type' => 'closure' ) );
$resolved_staff_leave = parisan_core_resolve_effective_day_schedule( '2026-11-09', $weekly, $staff_leave, null );
parisan_test_expect( ! empty( $resolved_staff_leave['off'] ) && 'staff_closure' === $resolved_staff_leave['source'], 'a specialist closure overrides their weekly rule' );
$salon_open_during_staff_leave = parisan_core_resolve_effective_day_schedule( '2026-11-09', $weekly, $staff_leave, $special_override );
parisan_test_expect( 'staff_closure' === $salon_open_during_staff_leave['source'], 'a specialist closure still wins over a salon special day' );

$special_slots = parisan_core_calculate_available_slots( '2026-11-08', $weekly, 'Europe/Berlin', 60, 30, array(), array(), 0, 0, null, $special_override );
parisan_test_expect( 5 === count( $special_slots ) && '2026-11-08 10:00' === $special_slots[0]['starts_at_local'], 'a special working day opens a normally closed weekday with custom hours' );
parisan_test_expect( ! in_array( '2026-11-08 12:00', array_column( $special_slots, 'starts_at_local' ), true ), 'special-day break blocks overlapping slots' );

$staff_custom = parisan_core_normalize_date_override( array( 'staff_id' => 12, 'override_date' => '2026-11-09', 'override_type' => 'custom_hours', 'start' => '15:00', 'end' => '18:00' ) );
$staff_custom_slots = parisan_core_calculate_available_slots( '2026-11-09', $weekly, 'Europe/Berlin', 60, 30, array(), array(), 0, 0, $staff_custom, null );
parisan_test_expect( 5 === count( $staff_custom_slots ) && '2026-11-09 15:00' === $staff_custom_slots[0]['starts_at_local'], 'a specialist date override replaces that specialist weekly hours' );

$dst_special = parisan_core_normalize_date_override( array( 'override_date' => '2026-03-29', 'override_type' => 'special_day', 'start' => '01:00', 'end' => '04:00' ) );
$dst_slots = parisan_core_calculate_available_slots( '2026-03-29', $weekly, 'Europe/Berlin', 30, 30, array(), array(), 0, 0, null, $dst_special );
$dst_local_starts = array_column( $dst_slots, 'starts_at_local' );
parisan_test_expect( 4 === count( $dst_slots ) && ! preg_grep( '/ 02:/', $dst_local_starts ) && in_array( '2026-03-29 03:00', $dst_local_starts, true ), 'DST spring-forward custom hours skip the nonexistent local hour' );

$midnight_weekly = parisan_core_normalize_weekly_schedule( array( 5 => array( 'start' => '00:00', 'end' => '02:00' ) ) );
$midnight_slots = parisan_core_calculate_available_slots( '2026-11-13', $midnight_weekly, 'Asia/Tehran', 30, 30 );
parisan_test_expect( 4 === count( $midnight_slots ) && '2026-11-12 20:30:00' === $midnight_slots[0]['starts_at_utc'], 'midnight local slots convert to the previous UTC date without changing the salon date' );

echo "\nSchedule tests: {$passed} passed, {$failed} failed.\n";
exit( 0 === $failed ? 0 : 1 );
