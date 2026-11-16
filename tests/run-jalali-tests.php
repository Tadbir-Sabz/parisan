<?php
/**
 * Standalone tests for the canonical Jalali date service.
 *
 * Run: php tests/run-jalali-tests.php
 *
 * @package ParisanCore
 */

require_once __DIR__ . '/../plugin/parisan-core/includes/jalali.php';

$passed = 0;
$failed = 0;

/**
 * Record a test assertion.
 *
 * @param bool   $condition Assertion result.
 * @param string $message Assertion description.
 * @return void
 */
function parisan_jalali_test_expect( $condition, $message ) {
	global $passed, $failed;

	if ( $condition ) {
		++$passed;
		echo "PASS: {$message}\n";
	} else {
		++$failed;
		fwrite( STDERR, "FAIL: {$message}\n" );
	}
}

$known_dates = array(
	array( '2024-03-20', 1403, 1, 1 ),
	array( '2025-03-20', 1403, 12, 30 ),
	array( '2025-03-21', 1404, 1, 1 ),
	array( '2026-03-21', 1405, 1, 1 ),
	array( '2026-11-13', 1405, 8, 22 ),
	array( '1979-02-11', 1357, 11, 22 ),
	array( '2021-03-20', 1399, 12, 30 ),
	array( '2021-03-21', 1400, 1, 1 ),
	array( '2030-03-20', 1408, 12, 30 ),
);
foreach ( $known_dates as $known ) {
	list( $gregorian, $jy, $jm, $jd ) = $known;
	$parts = parisan_core_gregorian_string_to_jalali( $gregorian );
	parisan_jalali_test_expect( is_array( $parts ) && $parts['year'] === $jy && $parts['month'] === $jm && $parts['day'] === $jd, $gregorian . ' converts to ' . $jy . '/' . $jm . '/' . $jd );
	parisan_jalali_test_expect( $gregorian === parisan_core_jalali_to_gregorian_string( $jy, $jm, $jd ), $jy . '/' . $jm . '/' . $jd . ' converts back to ' . $gregorian );
}

foreach ( array( 1399, 1403, 1408, 3177 ) as $leap_year ) {
	parisan_jalali_test_expect( parisan_core_jalali_is_leap_year( $leap_year ), $leap_year . ' is recognized as a Jalali leap year' );
	parisan_jalali_test_expect( 30 === parisan_core_jalali_month_length( $leap_year, 12 ), $leap_year . ' has a 30-day Esfand' );
}
foreach ( array( 1398, 1400, 1401, 1402, 1404, 1405, 1406, 1407 ) as $common_year ) {
	parisan_jalali_test_expect( ! parisan_core_jalali_is_leap_year( $common_year ), $common_year . ' is a common Jalali year' );
	parisan_jalali_test_expect( 29 === parisan_core_jalali_month_length( $common_year, 12 ), $common_year . ' has a 29-day Esfand' );
}
parisan_jalali_test_expect( false === parisan_core_jalali_to_gregorian( 1404, 12, 30 ), 'rejects Esfand 30 in a common year instead of rolling into the next year' );
parisan_jalali_test_expect( false === parisan_core_jalali_to_gregorian( 1405, 7, 31 ), 'rejects day 31 in the 30-day second half of the year' );
parisan_jalali_test_expect( false === parisan_core_jalali_to_gregorian( 1405, 13, 1 ), 'rejects an out-of-range Jalali month' );
parisan_jalali_test_expect( false === parisan_core_jalali_to_gregorian( 0, 1, 1 ), 'rejects a Jalali year below the supported range' );
parisan_jalali_test_expect( false === parisan_core_gregorian_to_jalali( 2026, 2, 29 ), 'rejects an impossible Gregorian date' );
parisan_jalali_test_expect( false === parisan_core_gregorian_to_jalali( 2026, 2, 30 ), 'rejects February 30' );
parisan_jalali_test_expect( false === parisan_core_gregorian_to_jalali( 2026, 4, 31 ), 'rejects April 31' );

parisan_jalali_test_expect( parisan_core_jalali_month_length( 1405, 1 ) === 31 && parisan_core_jalali_month_length( 1405, 6 ) === 31, 'first six Jalali months have 31 days' );
parisan_jalali_test_expect( parisan_core_jalali_month_length( 1405, 7 ) === 30 && parisan_core_jalali_month_length( 1405, 11 ) === 30, 'months 7 through 11 have 30 days' );
parisan_jalali_test_expect( 0 === parisan_core_jalali_month_length( 1405, 0 ) && 0 === parisan_core_jalali_month_length( 1405, 13 ), 'invalid Jalali months have no length' );

$minimum = parisan_core_jalali_to_gregorian_string( 1, 1, 1 );
$maximum = parisan_core_jalali_to_gregorian_string( 3177, 12, 30 );
parisan_jalali_test_expect( is_string( $minimum ) && 0 === strpos( $minimum, '0622-' ), 'supported Jalali range begins in Gregorian 622' );
parisan_jalali_test_expect( is_string( $maximum ) && 0 === strpos( $maximum, '3799-' ), 'supported Jalali range extends to Gregorian 3799' );

$round_trip_failures = 0;
$cursor = new DateTimeImmutable( '1900-01-01', new DateTimeZone( 'UTC' ) );
$end = new DateTimeImmutable( '2100-12-31', new DateTimeZone( 'UTC' ) );
	while ( $cursor <= $end ) {
	$parts = parisan_core_gregorian_string_to_jalali( $cursor->format( 'Y-m-d' ) );
	$back = false === $parts ? false : parisan_core_jalali_to_gregorian_string( $parts['year'], $parts['month'], $parts['day'] );
	if ( false === $parts || $cursor->format( 'Y-m-d' ) !== $back ) {
		++$round_trip_failures;
	}
	$cursor = $cursor->modify( '+17 days' );
}
parisan_jalali_test_expect( 0 === $round_trip_failures, 'Gregorian/Jalali round trips are lossless across 1900-2100 sample days' );

parisan_jalali_test_expect( 'فروردین' === parisan_core_jalali_month_name( 1 ) && 'اسفند' === parisan_core_jalali_month_name( 12 ), 'Persian Jalali month names are canonical' );
parisan_jalali_test_expect( 'Farvardin' === parisan_core_jalali_month_name( 1, 'en' ) && 'Esfand' === parisan_core_jalali_month_name( 12, 'en' ), 'English transliterations are available for non-Persian locales' );
parisan_jalali_test_expect( 'شنبه' === parisan_core_jalali_weekday_name( 0 ) && 'جمعه' === parisan_core_jalali_weekday_name( 6 ), 'Persian week starts on Saturday and ends on Friday' );
parisan_jalali_test_expect( 0 === parisan_core_jalali_weekday_index_from_php( 6 ) && 6 === parisan_core_jalali_weekday_index_from_php( 5 ), 'PHP weekday numbers map to the Persian week correctly' );
parisan_jalali_test_expect( '۱۴۰۵' === parisan_core_to_persian_digits( '1405' ) && '1405' === parisan_core_to_latin_digits( '۱۴۰۵' ), 'Persian and Latin digit presentation is reversible' );

$formatted = parisan_core_format_jalali_date(
	'2026-11-13',
	array(
		'with_weekday' => true,
	)
);
parisan_jalali_test_expect( 'جمعه، ۲۲ آبان ۱۴۰۵' === $formatted, 'default Persian formatting includes weekday, day, month, and year with Persian digits' );
$formatted_en = parisan_core_format_jalali_date(
	'2026-11-13',
	array(
		'locale'        => 'en',
		'with_weekday'  => true,
	)
);
parisan_jalali_test_expect( 'Friday, 22 Aban 1405' === $formatted_en, 'English locale formatting uses transliterated labels and Latin digits' );
$anchored = parisan_core_format_jalali_date(
	'2026-11-13',
	array(
		'timezone'     => 'Pacific/Kiritimati',
		'with_weekday' => true,
	)
);
parisan_jalali_test_expect( 'جمعه، ۲۲ آبان ۱۴۰۵' === $anchored, 'a display timezone cannot roll a date label into the next or previous day' );

$instant = new DateTimeImmutable( '2026-11-13 10:30:00', new DateTimeZone( 'UTC' ) );
$tehran_label = parisan_core_format_jalali_datetime(
	$instant,
	array(
		'timezone'    => 'Asia/Tehran',
		'with_time'   => true,
		'with_weekday' => true,
	)
);
parisan_jalali_test_expect( 'جمعه، ۲۲ آبان ۱۴۰۵، ساعت ۱۴:۰۰' === $tehran_label, 'formatting converts UTC instants to the configured timezone before deriving the Jalali date' );
$utc_label = parisan_core_format_jalali_datetime(
	$instant,
	array(
		'timezone'  => 'UTC',
		'with_time' => true,
		'with_weekday' => true,
	)
);
parisan_jalali_test_expect( 'جمعه، ۲۲ آبان ۱۴۰۵، ساعت ۱۰:۳۰' === $utc_label, 'the same instant formats differently in UTC without corrupting the instant' );
$boundary_label = parisan_core_format_jalali_datetime(
	new DateTimeImmutable( '2026-11-13 21:00:00', new DateTimeZone( 'UTC' ) ),
	array(
		'timezone' => 'Asia/Tehran',
		'with_time' => true,
		'with_weekday' => true,
	)
);
parisan_jalali_test_expect( 0 === strpos( $boundary_label, 'شنبه، ۲۳ آبان ۱۴۰۵' ), 'a UTC evening instant crosses into the next Jalali day in Tehran' );

parisan_jalali_test_expect( '2026-11-13' === parisan_core_parse_jalali_date_input( '۱۴۰۵/۰۸/۲۲' ), 'Persian-digit Jalali form input parses to canonical Gregorian date' );
parisan_jalali_test_expect( '2026-11-13' === parisan_core_parse_jalali_date_input( '1405-8-22' ), 'non-zero-padded Jalali input is accepted' );
parisan_jalali_test_expect( false === parisan_core_parse_jalali_date_input( '۱۴۰۴/۱۲/۳۰' ), 'invalid Jalali form input fails closed' );
parisan_jalali_test_expect( false === parisan_core_parse_jalali_date_input( '2026-11-13' ), 'native Gregorian strings are not silently reinterpreted as Jalali' );

$grid = parisan_core_jalali_month_grid( 1405, 8 );
parisan_jalali_test_expect( 42 === count( $grid ), 'month grid always renders six complete Persian weeks' );
parisan_jalali_test_expect( 0 === $grid[0]['weekday'] && 'شنبه' === $grid[0]['weekday_fa'], 'month grid begins on a Saturday cell' );
parisan_jalali_test_expect( '2026-10-17' === $grid[0]['gregorian'], 'Aban 1405 grid starts on the correct leading Gregorian date' );
parisan_jalali_test_expect( '2026-10-23' === $grid[6]['gregorian'] && 1405 === $grid[6]['year'] && 8 === $grid[6]['month'] && 1 === $grid[6]['day'], 'first day of Aban 1405 is placed in the seventh Saturday-started cell' );
$in_month = array_values( array_filter( $grid, static function ( $cell ) { return $cell['in_month']; } ) );
parisan_jalali_test_expect( 30 === count( $in_month ) && '2026-11-21' === $in_month[29]['gregorian'], 'Aban 1405 exposes exactly 30 in-month cells ending on 30 Aban' );
parisan_jalali_test_expect( 1405 === $grid[41]['year'] && 9 === $grid[41]['month'], 'grid tail crosses into the next Jalali month' );
$next_month = parisan_core_jalali_add_months( 1405, 12, 1 );
$previous_month = parisan_core_jalali_add_months( 1405, 1, -1 );
parisan_jalali_test_expect( array( 1406, 1 ) === $next_month && array( 1404, 12 ) === $previous_month, 'Jalali month navigation crosses year boundaries correctly' );

echo "\nJalali tests: {$passed} passed, {$failed} failed.\n";
exit( 0 === $failed ? 0 : 1 );
