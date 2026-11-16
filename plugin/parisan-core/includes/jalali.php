<?php
/**
 * Canonical Gregorian/Jalali conversion and Persian presentation helpers.
 *
 * This file is intentionally WordPress-independent so the same conversion
 * code can be tested as a pure library and reused by admin, public, REST, and
 * booking layers. Persisted instants remain UTC; callers convert to the salon
 * timezone before requesting a Jalali label.
 *
 * Conversion uses the arithmetic 33-year Jalali cycle and is supported for
 * Jalali years 1..3177 (Gregorian 622..3799). Every public conversion is
 * cross-checked by a reverse conversion, so invalid Esfand 30 dates fail
 * closed instead of silently rolling into the next year.
 *
 * @package ParisanCore
 */

/**
 * Return the supported conversion bounds.
 *
 * @return array<string,int>
 */
function parisan_core_jalali_bounds() {
	return array(
		'min_gregorian_year' => 622,
		'max_gregorian_year' => 3799,
		'min_jalali_year'    => 1,
		'max_jalali_year'    => 3177,
	);
}

/**
 * Whether a Gregorian year is a leap year.
 *
 * @param int $year Gregorian year.
 * @return bool
 */
function parisan_core_jalali_gregorian_is_leap( $year ) {
	$year = (int) $year;
	return ( 0 === $year % 4 && 0 !== $year % 100 ) || 0 === $year % 400;
}

/**
 * Number of days in a Gregorian month.
 *
 * @param int $year Gregorian year.
 * @param int $month Gregorian month.
 * @return int
 */
function parisan_core_jalali_gregorian_month_length( $year, $month ) {
	$lengths = array( 31, parisan_core_jalali_gregorian_is_leap( $year ) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
	$month = (int) $month;
	return $month >= 1 && $month <= 12 ? $lengths[ $month - 1 ] : 0;
}

/**
 * Whether a Gregorian date is valid and inside the supported range.
 *
 * @param int $year Gregorian year.
 * @param int $month Gregorian month.
 * @param int $day Gregorian day.
 * @return bool
 */
function parisan_core_jalali_is_valid_gregorian( $year, $month, $day ) {
	$year = (int) $year;
	$month = (int) $month;
	$day = (int) $day;
	$bounds = parisan_core_jalali_bounds();
	return $year >= $bounds['min_gregorian_year'] && $year <= $bounds['max_gregorian_year'] && $month >= 1 && $month <= 12 && $day >= 1 && $day <= parisan_core_jalali_gregorian_month_length( $year, $month );
}

/**
 * Raw Gregorian-to-Jalali arithmetic without range validation.
 *
 * @param int $gy Gregorian year.
 * @param int $gm Gregorian month.
 * @param int $gd Gregorian day.
 * @return array<int,int>
 */
function parisan_core_jalali_raw_gregorian_to_jalali( $gy, $gm, $gd ) {
	$g_days_in_month = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
	$gy2 = $gm > 2 ? $gy + 1 : $gy;
	$days = 355666 + ( 365 * $gy ) + (int) ( ( $gy2 + 3 ) / 4 ) - (int) ( ( $gy2 + 99 ) / 100 ) + (int) ( ( $gy2 + 399 ) / 400 ) + $gd + $g_days_in_month[ $gm - 1 ];
	$jy = -1595 + ( 33 * (int) ( $days / 12053 ) );
	$days %= 12053;
	$jy += 4 * (int) ( $days / 1461 );
	$days %= 1461;
	if ( $days > 365 ) {
		$jy += (int) ( ( $days - 1 ) / 365 );
		$days = ( $days - 1 ) % 365;
	}
	if ( $days < 186 ) {
		$jm = 1 + (int) ( $days / 31 );
		$jd = 1 + ( $days % 31 );
	} else {
		$jm = 7 + (int) ( ( $days - 186 ) / 30 );
		$jd = 1 + ( ( $days - 186 ) % 30 );
	}
	return array( $jy, $jm, $jd );
}

/**
 * Raw Jalali-to-Gregorian arithmetic without range validation.
 *
 * @param int $jy Jalali year.
 * @param int $jm Jalali month.
 * @param int $jd Jalali day.
 * @return array<int,int>
 */
function parisan_core_jalali_raw_jalali_to_gregorian( $jy, $jm, $jd ) {
	$jy += 1595;
	$days = -355668 + ( 365 * $jy ) + ( ( (int) ( $jy / 33 ) ) * 8 ) + (int) ( ( ( $jy % 33 ) + 3 ) / 4 ) + $jd + ( $jm < 7 ? ( $jm - 1 ) * 31 : ( ( $jm - 7 ) * 30 ) + 186 );
	$gy = 400 * (int) ( $days / 146097 );
	$days %= 146097;
	if ( $days > 36524 ) {
		--$days;
		$gy += 100 * (int) ( $days / 36524 );
		$days %= 36524;
		if ( $days >= 365 ) {
			++$days;
		}
	}
	$gy += 4 * (int) ( $days / 1461 );
	$days %= 1461;
	if ( $days > 365 ) {
		$gy += (int) ( ( $days - 1 ) / 365 );
		$days = ( $days - 1 ) % 365;
	}
	$gd = $days + 1;
	$month_lengths = array( 0, 31, parisan_core_jalali_gregorian_is_leap( $gy ) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
	$gm = 0;
	while ( $gm < 12 && $gd > $month_lengths[ $gm + 1 ] ) {
		$gd -= $month_lengths[ $gm + 1 ];
		++$gm;
	}
	return array( $gy, $gm + 1, $gd );
}

/**
 * Whether a Jalali year is a leap year (Esfand has 30 days).
 *
 * @param int $year Jalali year.
 * @return bool
 */
function parisan_core_jalali_is_leap_year( $year ) {
	$year = (int) $year;
	$bounds = parisan_core_jalali_bounds();
	if ( $year < $bounds['min_jalali_year'] || $year > $bounds['max_jalali_year'] ) {
		return false;
	}
	$gregorian = parisan_core_jalali_raw_jalali_to_gregorian( $year, 12, 30 );
	$round_trip = parisan_core_jalali_raw_gregorian_to_jalali( $gregorian[0], $gregorian[1], $gregorian[2] );
	return (int) $round_trip[0] === $year && 12 === (int) $round_trip[1] && 30 === (int) $round_trip[2];
}

/**
 * Number of days in a Jalali month.
 *
 * @param int $year Jalali year.
 * @param int $month Jalali month.
 * @return int
 */
function parisan_core_jalali_month_length( $year, $month ) {
	$year = (int) $year;
	$month = (int) $month;
	$bounds = parisan_core_jalali_bounds();
	if ( $year < $bounds['min_jalali_year'] || $year > $bounds['max_jalali_year'] || $month < 1 || $month > 12 ) {
		return 0;
	}
	if ( $month <= 6 ) {
		return 31;
	}
	if ( $month <= 11 ) {
		return 30;
	}
	return parisan_core_jalali_is_leap_year( $year ) ? 30 : 29;
}

/**
 * Convert a Gregorian date to Jalali with strict validation.
 *
 * @param int $year Gregorian year.
 * @param int $month Gregorian month.
 * @param int $day Gregorian day.
 * @return array<int,int>|false [Jalali year, month, day].
 */
function parisan_core_gregorian_to_jalali( $year, $month, $day ) {
	$year = (int) $year;
	$month = (int) $month;
	$day = (int) $day;
	if ( ! parisan_core_jalali_is_valid_gregorian( $year, $month, $day ) ) {
		return false;
	}
	$jalali = parisan_core_jalali_raw_gregorian_to_jalali( $year, $month, $day );
	$bounds = parisan_core_jalali_bounds();
	if ( $jalali[0] < $bounds['min_jalali_year'] || $jalali[0] > $bounds['max_jalali_year'] ) {
		return false;
	}
	$round_trip = parisan_core_jalali_raw_jalali_to_gregorian( $jalali[0], $jalali[1], $jalali[2] );
	if ( $year !== $round_trip[0] || $month !== $round_trip[1] || $day !== $round_trip[2] ) {
		return false;
	}
	return array( (int) $jalali[0], (int) $jalali[1], (int) $jalali[2] );
}

/**
 * Convert a Jalali date to Gregorian with strict validation.
 *
 * @param int $year Jalali year.
 * @param int $month Jalali month.
 * @param int $day Jalali day.
 * @return array<int,int>|false [Gregorian year, month, day].
 */
function parisan_core_jalali_to_gregorian( $year, $month, $day ) {
	$year = (int) $year;
	$month = (int) $month;
	$day = (int) $day;
	$length = parisan_core_jalali_month_length( $year, $month );
	if ( $length < 1 || $day < 1 || $day > $length ) {
		return false;
	}
	$gregorian = parisan_core_jalali_raw_jalali_to_gregorian( $year, $month, $day );
	if ( ! parisan_core_jalali_is_valid_gregorian( $gregorian[0], $gregorian[1], $gregorian[2] ) ) {
		return false;
	}
	$round_trip = parisan_core_jalali_raw_gregorian_to_jalali( $gregorian[0], $gregorian[1], $gregorian[2] );
	if ( $year !== $round_trip[0] || $month !== $round_trip[1] || $day !== $round_trip[2] ) {
		return false;
	}
	return array( (int) $gregorian[0], (int) $gregorian[1], (int) $gregorian[2] );
}

/**
 * Return a normalized Gregorian Y-m-d string for a Jalali date.
 *
 * @param int $year Jalali year.
 * @param int $month Jalali month.
 * @param int $day Jalali day.
 * @return string|false
 */
function parisan_core_jalali_to_gregorian_string( $year, $month, $day ) {
	$gregorian = parisan_core_jalali_to_gregorian( $year, $month, $day );
	return false === $gregorian ? false : sprintf( '%04d-%02d-%02d', $gregorian[0], $gregorian[1], $gregorian[2] );
}

/**
 * Convert a Gregorian Y-m-d value to normalized Jalali parts.
 *
 * @param string $date Gregorian date in Y-m-d format.
 * @return array<string,int>|false
 */
function parisan_core_gregorian_string_to_jalali( $date ) {
	if ( ! is_string( $date ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $matches ) ) {
		return false;
	}
	$jalali = parisan_core_gregorian_to_jalali( (int) $matches[1], (int) $matches[2], (int) $matches[3] );
	if ( false === $jalali ) {
		return false;
	}
	return array( 'year' => $jalali[0], 'month' => $jalali[1], 'day' => $jalali[2] );
}

/**
 * Normalize Persian/Arabic digits to ASCII digits.
 *
 * @param mixed $value Input value.
 * @return string
 */
function parisan_core_to_latin_digits( $value ) {
	if ( ! is_scalar( $value ) ) {
		return '';
	}
	return strtr(
		(string) $value,
		array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		)
	);
}

/**
 * Convert ASCII digits in a scalar value to Persian digits.
 *
 * @param mixed $value Input value.
 * @return string
 */
function parisan_core_to_persian_digits( $value ) {
	if ( ! is_scalar( $value ) ) {
		return '';
	}
	return strtr(
		(string) $value,
		array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' )
	);
}

/**
 * Parse a Jalali date supplied by a Persian-facing form.
 *
 * Accepts Persian/Arabic digits and /, -, or . separators. Returns a normalized
 * Gregorian Y-m-d string so database and REST boundaries remain Gregorian.
 *
 * @param mixed $input Untrusted form value.
 * @return string|false
 */
function parisan_core_parse_jalali_date_input( $input ) {
	if ( ! is_scalar( $input ) ) {
		return false;
	}
	$value = trim( parisan_core_to_latin_digits( $input ) );
	if ( ! preg_match( '/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})$/D', $value, $matches ) ) {
		return false;
	}
	$year = (int) $matches[1];
	// Persian-facing forms use contemporary Jalali years. Rejecting a year such
	// as 2026 prevents a Gregorian value from being silently reinterpreted as a
	// far-future Jalali date while the conversion service keeps its wider range.
	if ( $year < 1200 || $year > 1600 ) {
		return false;
	}
	return parisan_core_jalali_to_gregorian_string( $year, (int) $matches[2], (int) $matches[3] );
}

/**
 * Return Jalali month names keyed by month number.
 *
 * @param string $locale Presentation locale; fa or en.
 * @return array<int,string>
 */
function parisan_core_jalali_month_names( $locale = 'fa' ) {
	if ( 'fa' === $locale ) {
		return array( 1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند' );
	}
	return array( 1 => 'Farvardin', 2 => 'Ordibehesht', 3 => 'Khordad', 4 => 'Tir', 5 => 'Mordad', 6 => 'Shahrivar', 7 => 'Mehr', 8 => 'Aban', 9 => 'Azar', 10 => 'Dey', 11 => 'Bahman', 12 => 'Esfand' );
}

/**
 * Return Persian week weekday names starting on Saturday.
 *
 * @param string $locale Presentation locale; fa or en.
 * @return array<int,string>
 */
function parisan_core_jalali_weekday_names( $locale = 'fa' ) {
	if ( 'fa' === $locale ) {
		return array( 0 => 'شنبه', 1 => 'یکشنبه', 2 => 'دوشنبه', 3 => 'سه‌شنبه', 4 => 'چهارشنبه', 5 => 'پنجشنبه', 6 => 'جمعه' );
	}
	return array( 0 => 'Saturday', 1 => 'Sunday', 2 => 'Monday', 3 => 'Tuesday', 4 => 'Wednesday', 5 => 'Thursday', 6 => 'Friday' );
}

/**
 * Return a Jalali month label.
 *
 * @param int $month Jalali month.
 * @param string $locale Presentation locale.
 * @return string
 */
function parisan_core_jalali_month_name( $month, $locale = 'fa' ) {
	$names = parisan_core_jalali_month_names( $locale );
	$month = (int) $month;
	return isset( $names[ $month ] ) ? $names[ $month ] : '';
}

/**
 * Return a Persian-week weekday name.
 *
 * @param int $index 0 = Saturday ... 6 = Friday.
 * @param string $locale Presentation locale.
 * @return string
 */
function parisan_core_jalali_weekday_name( $index, $locale = 'fa' ) {
	$names = parisan_core_jalali_weekday_names( $locale );
	$index = (int) $index;
	return isset( $names[ $index ] ) ? $names[ $index ] : '';
}

/**
 * Convert a PHP weekday number (0 = Sunday) to the Persian week (0 = Saturday).
 *
 * @param int $php_weekday PHP `w` value.
 * @return int
 */
function parisan_core_jalali_weekday_index_from_php( $php_weekday ) {
	return ( (int) $php_weekday + 1 ) % 7;
}

/**
 * Return Jalali parts and weekday metadata for an instant in a timezone.
 *
 * @param DateTimeInterface $datetime Instant to convert.
 * @param DateTimeZone|string|null $timezone Display timezone; defaults to the instant timezone.
 * @return array<string,mixed>|false
 */
function parisan_core_jalali_parts_from_datetime( $datetime, $timezone = null ) {
	if ( ! $datetime instanceof DateTimeInterface ) {
		return false;
	}
	try {
		$local = $datetime instanceof DateTimeImmutable ? $datetime : DateTimeImmutable::createFromInterface( $datetime );
		if ( null !== $timezone ) {
			$timezone = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone( (string) $timezone );
			$local = $local->setTimezone( $timezone );
		}
	} catch ( Exception $exception ) {
		return false;
	}
	$jalali = parisan_core_gregorian_to_jalali( (int) $local->format( 'Y' ), (int) $local->format( 'n' ), (int) $local->format( 'j' ) );
	if ( false === $jalali ) {
		return false;
	}
	$weekday = parisan_core_jalali_weekday_index_from_php( (int) $local->format( 'w' ) );
	return array(
		'year'          => $jalali[0],
		'month'         => $jalali[1],
		'day'           => $jalali[2],
		'weekday'       => $weekday,
		'gregorian_date' => $local->format( 'Y-m-d' ),
		'time'          => $local->format( 'H:i' ),
		'timezone'      => $local->getTimezone()->getName(),
	);
}

/**
 * Format an instant with a Jalali calendar label.
 *
 * Options:
 * - timezone: DateTimeZone or IANA name.
 * - locale: fa (default) or en.
 * - persian_digits: bool; defaults to true for fa.
 * - with_time: bool.
 * - with_weekday: bool.
 *
 * @param DateTimeInterface $datetime Instant to display.
 * @param array<string,mixed> $options Presentation options.
 * @return string
 */
function parisan_core_format_jalali_datetime( $datetime, $options = array() ) {
	$options = is_array( $options ) ? $options : array();
	$locale = isset( $options['locale'] ) && 'en' === $options['locale'] ? 'en' : 'fa';
	$timezone = isset( $options['timezone'] ) ? $options['timezone'] : null;
	$parts = parisan_core_jalali_parts_from_datetime( $datetime, $timezone );
	if ( false === $parts ) {
		return '';
	}
	$persian_digits = isset( $options['persian_digits'] ) ? (bool) $options['persian_digits'] : 'fa' === $locale;
	$with_time = ! empty( $options['with_time'] );
	$with_weekday = ! empty( $options['with_weekday'] );
	$number = static function ( $value ) use ( $persian_digits ) {
		return $persian_digits ? parisan_core_to_persian_digits( $value ) : (string) $value;
	};
	$date_label = $number( $parts['day'] ) . ' ' . parisan_core_jalali_month_name( $parts['month'], $locale ) . ' ' . $number( $parts['year'] );
	if ( $with_weekday ) {
		$date_label = parisan_core_jalali_weekday_name( $parts['weekday'], $locale ) . ( 'fa' === $locale ? '، ' : ', ' ) . $date_label;
	}
	if ( $with_time ) {
		$date_label .= ( 'fa' === $locale ? '، ساعت ' : ' at ' ) . $number( $parts['time'] );
	}
	return $date_label;
}

/**
 * Format a Gregorian Y-m-d local date with month and weekday labels.
 *
 * @param string $date Gregorian Y-m-d.
 * @param array<string,mixed> $options Presentation options.
 * @return string
 */
function parisan_core_format_jalali_date( $date, $options = array() ) {
	$parts = parisan_core_gregorian_string_to_jalali( $date );
	if ( false === $parts ) {
		return '';
	}
	$options = is_array( $options ) ? $options : array();
	$timezone = new DateTimeZone( 'UTC' );
	if ( isset( $options['timezone'] ) ) {
		try {
			$timezone = $options['timezone'] instanceof DateTimeZone ? $options['timezone'] : new DateTimeZone( (string) $options['timezone'] );
		} catch ( Exception $exception ) {
			return '';
		}
	}
	try {
		// Format the requested calendar date at noon in its own timezone. A
		// caller-supplied display timezone must never roll the date to another
		// day while producing its label.
		$instant = new DateTimeImmutable( $date . ' 12:00:00', $timezone );
	} catch ( Exception $exception ) {
		return '';
	}
	unset( $options['with_time'], $options['timezone'] );
	return parisan_core_format_jalali_datetime( $instant, $options );
}

/**
 * Build a six-week Jalali month grid starting on Saturday.
 *
 * Each cell contains Jalali and Gregorian identifiers plus whether it belongs
 * to the requested month. The grid is presentation-independent and is used by
 * the admin calendar and its tests.
 *
 * @param int $year Jalali year.
 * @param int $month Jalali month.
 * @return array<int,array<string,mixed>>
 */
function parisan_core_jalali_month_grid( $year, $month ) {
	$year = (int) $year;
	$month = (int) $month;
	$month_length = parisan_core_jalali_month_length( $year, $month );
	$first = parisan_core_jalali_to_gregorian( $year, $month, 1 );
	if ( $month_length < 1 || false === $first ) {
		return array();
	}
	try {
		$timezone = new DateTimeZone( 'UTC' );
		$first_date = new DateTimeImmutable( sprintf( '%04d-%02d-%02d 12:00:00', $first[0], $first[1], $first[2] ), $timezone );
	} catch ( Exception $exception ) {
		return array();
	}
	$leading = parisan_core_jalali_weekday_index_from_php( (int) $first_date->format( 'w' ) );
	$cursor = $first_date->modify( '-' . $leading . ' days' );
	$cells = array();
	for ( $index = 0; $index < 42; $index++ ) {
		$gregorian = $cursor->format( 'Y-m-d' );
		$parts = parisan_core_gregorian_string_to_jalali( $gregorian );
		if ( false === $parts ) {
			return array();
		}
		$weekday = parisan_core_jalali_weekday_index_from_php( (int) $cursor->format( 'w' ) );
		$cells[] = array(
			'gregorian'  => $gregorian,
			'year'       => $parts['year'],
			'month'      => $parts['month'],
			'day'        => $parts['day'],
			'in_month'   => $parts['year'] === $year && $parts['month'] === $month,
			'weekday'    => $weekday,
			'weekday_fa' => parisan_core_jalali_weekday_name( $weekday, 'fa' ),
			'weekday_en' => parisan_core_jalali_weekday_name( $weekday, 'en' ),
		);
		$cursor = $cursor->modify( '+1 day' );
	}
	return $cells;
}

/**
 * Add months to a Jalali year/month pair without date rollover surprises.
 *
 * @param int $year Jalali year.
 * @param int $month Jalali month.
 * @param int $delta Month delta.
 * @return array<int,int>|false
 */
function parisan_core_jalali_add_months( $year, $month, $delta ) {
	$index = ( (int) $year * 12 ) + ( (int) $month - 1 ) + (int) $delta;
	$new_year = (int) floor( $index / 12 );
	$new_month = ( $index % 12 ) + 1;
	$bounds = parisan_core_jalali_bounds();
	if ( $new_year < $bounds['min_jalali_year'] || $new_year > $bounds['max_jalali_year'] ) {
		return false;
	}
	return array( $new_year, $new_month );
}
