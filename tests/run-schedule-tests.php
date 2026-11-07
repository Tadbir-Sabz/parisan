<?php
/**
 * Standalone tests for the pure weekly schedule normalizer.
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

parisan_test_expect(
	array( 'off' => false, 'start' => '09:00', 'end' => '18:00' ) === $valid[6],
	'accepts a valid Saturday time window'
);
parisan_test_expect(
	array( 'off' => true ) === $valid[0],
	'respects an explicitly closed day'
);
parisan_test_expect(
	array( 'off' => false, 'start' => '10:15', 'end' => '16:45' ) === $valid[1],
	'accepts zero-padded 24-hour times'
);
parisan_test_expect(
	array( 'off' => true ) === $valid[2],
	'missing day defaults to unavailable'
);
parisan_test_expect(
	array( 'off' => true ) === parisan_core_normalize_weekly_schedule(
		array( 3 => array( 'start' => '9:00', 'end' => '18:00' ) )
	)[3],
	'rejects non-zero-padded time input'
);
parisan_test_expect(
	array( 'off' => true ) === parisan_core_normalize_weekly_schedule(
		array( 3 => array( 'start' => '17:00', 'end' => '09:00' ) )
	)[3],
	'rejects end time earlier than start time'
);
parisan_test_expect(
	array( 'off' => true ) === parisan_core_normalize_weekly_schedule(
		array( 3 => array( 'start' => '09:00', 'end' => '24:00' ) )
	)[3],
	'rejects invalid 24:00 clock value'
);
parisan_test_expect(
	array( 'off' => true ) === parisan_core_normalize_weekly_schedule(
		array( 3 => array( 'start' => array( 'unexpected' ), 'end' => '18:00' ) )
	)[3],
	'rejects non-scalar time input without warnings'
);
parisan_test_expect(
	7 === count( parisan_core_normalize_weekly_schedule( 'invalid' ) ),
	'returns all seven weekdays for malformed root input'
);

echo "\nSchedule tests: {$passed} passed, {$failed} failed.\n";
exit( 0 === $failed ? 0 : 1 );
