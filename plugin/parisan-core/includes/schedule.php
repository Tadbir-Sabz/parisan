<?php
/**
 * Pure schedule normalization, timezone, and availability helpers.
 *
 * @package ParisanCore
 */

/**
 * Normalize a weekly schedule into safe salon-local wall-clock windows.
 *
 * @param mixed $input Untrusted schedule input.
 * @return array<int,array<string,mixed>>
 */
function parisan_core_normalize_weekly_schedule( $input ) {
	$input = is_array( $input ) ? $input : array();
	$schedule = array();
	for ( $day = 0; $day <= 6; $day++ ) {
		$row = isset( $input[ $day ] ) && is_array( $input[ $day ] ) ? $input[ $day ] : array();
		$off = ! empty( $row['off'] );
		$start = isset( $row['start'] ) && is_string( $row['start'] ) ? $row['start'] : '';
		$end = isset( $row['end'] ) && is_string( $row['end'] ) ? $row['end'] : '';
		$valid_time = static function ( $time ) {
			return 1 === preg_match( '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $time );
		};
		if ( $off ) {
			$schedule[ $day ] = array( 'off' => true );
		} elseif ( $valid_time( $start ) && $valid_time( $end ) && $end > $start ) {
			$schedule[ $day ] = array( 'off' => false, 'start' => $start, 'end' => $end );
		} else {
			$schedule[ $day ] = array( 'off' => true );
		}
	}
	return $schedule;
}

/**
 * Convert a salon-local wall-clock value to UTC, rejecting DST-normalized gaps.
 *
 * @param string $date Date in Y-m-d format.
 * @param string $time Time in H:i format.
 * @param DateTimeZone $timezone Salon timezone.
 * @return DateTimeImmutable|false
 */
function parisan_core_local_datetime_to_utc( $date, $time, $timezone ) {
	if ( ! $timezone instanceof DateTimeZone || ! is_string( $date ) || ! is_string( $time ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $date ) || ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $time ) ) {
		return false;
	}
	$local = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $time, $timezone );
	$errors = DateTimeImmutable::getLastErrors();
	if ( false === $local || ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) || $local->format( 'Y-m-d H:i' ) !== $date . ' ' . $time ) {
		return false;
	}
	return $local->setTimezone( new DateTimeZone( 'UTC' ) );
}

/**
 * Convert a local interval into UTC with strict date/time and ordering checks.
 *
 * @param string $start_date Local start date.
 * @param string $start_time Local start time.
 * @param string $end_date Local end date.
 * @param string $end_time Local end time.
 * @param DateTimeZone $timezone Salon timezone.
 * @return array|false
 */
function parisan_core_local_interval_to_utc( $start_date, $start_time, $end_date, $end_time, $timezone ) {
	$start = parisan_core_local_datetime_to_utc( $start_date, $start_time, $timezone );
	$end = parisan_core_local_datetime_to_utc( $end_date, $end_time, $timezone );
	if ( false === $start || false === $end || $end <= $start ) {
		return false;
	}
	return array( 'start' => $start, 'end' => $end );
}

/**
 * Calculate candidate appointment starts for one salon-local calendar date.
 *
 * @param string $date Salon-local date.
 * @param array $weekly_schedule Normalized WordPress weekday schedule.
 * @param string $timezone_name IANA timezone identifier.
 * @param int $duration_minutes Service duration.
 * @param int $slot_interval_minutes Candidate start increment.
 * @param array $busy_intervals Existing booking intervals.
 * @param array $exceptions Unavailable intervals.
 * @param int $buffer_before_minutes Buffer before service.
 * @param int $buffer_after_minutes Buffer after service.
 * @return array<int,array<string,string>>
 */
function parisan_core_calculate_available_slots( $date, $weekly_schedule, $timezone_name, $duration_minutes, $slot_interval_minutes, $busy_intervals = array(), $exceptions = array(), $buffer_before_minutes = 0, $buffer_after_minutes = 0 ) {
	if ( ! is_string( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $date ) || ! is_array( $weekly_schedule ) ) {
		return array();
	}
	try {
		$timezone = new DateTimeZone( $timezone_name );
		$day_date = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, $timezone );
	} catch ( Exception $exception ) {
		return array();
	}
	$date_errors = DateTimeImmutable::getLastErrors();
	if ( false === $day_date || ( is_array( $date_errors ) && ( $date_errors['warning_count'] > 0 || $date_errors['error_count'] > 0 ) ) || $day_date->format( 'Y-m-d' ) !== $date ) {
		return array();
	}
	$day = (int) $day_date->format( 'w' );
	$hours = isset( $weekly_schedule[ $day ] ) && is_array( $weekly_schedule[ $day ] ) ? $weekly_schedule[ $day ] : array();
	$duration_minutes = max( 0, (int) $duration_minutes );
	$slot_interval_minutes = max( 0, (int) $slot_interval_minutes );
	$buffer_before_minutes = max( 0, (int) $buffer_before_minutes );
	$buffer_after_minutes = max( 0, (int) $buffer_after_minutes );
	if ( ! empty( $hours['off'] ) || empty( $hours['start'] ) || empty( $hours['end'] ) || 0 === $duration_minutes || 0 === $slot_interval_minutes ) {
		return array();
	}
	$opening = parisan_core_local_datetime_to_utc( $date, $hours['start'], $timezone );
	$closing = parisan_core_local_datetime_to_utc( $date, $hours['end'], $timezone );
	if ( false === $opening || false === $closing || $closing <= $opening ) {
		return array();
	}
	$normalize_intervals = static function ( $intervals ) {
		$normalized = array();
		foreach ( is_array( $intervals ) ? $intervals : array() as $interval ) {
			if ( ! is_array( $interval ) || ! isset( $interval['start'], $interval['end'] ) ) {
				continue;
			}
			try {
				$utc = new DateTimeZone( 'UTC' );
				$start = $interval['start'] instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface( $interval['start'] ) : new DateTimeImmutable( (string) $interval['start'], $utc );
				$end = $interval['end'] instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface( $interval['end'] ) : new DateTimeImmutable( (string) $interval['end'], $utc );
				$start = $start->setTimezone( $utc );
				$end = $end->setTimezone( $utc );
				if ( $end > $start ) {
					$normalized[] = array( 'start' => $start, 'end' => $end );
				}
			} catch ( Exception $exception ) {
				continue;
			}
		}
		return $normalized;
	};
	$busy = $normalize_intervals( $busy_intervals );
	$blocked = $normalize_intervals( $exceptions );
	$slots = array();
	$step = new DateInterval( 'PT' . $slot_interval_minutes . 'M' );
	$service_duration = new DateInterval( 'PT' . $duration_minutes . 'M' );
	$before = new DateInterval( 'PT' . $buffer_before_minutes . 'M' );
	$after = new DateInterval( 'PT' . $buffer_after_minutes . 'M' );
	for ( $candidate = $opening; $candidate < $closing; $candidate = $candidate->add( $step ) ) {
		$service_end = $candidate->add( $service_duration );
		$reserved_start = $candidate->sub( $before );
		$reserved_end = $service_end->add( $after );
		if ( $reserved_start < $opening || $reserved_end > $closing ) {
			continue;
		}
		$conflict = false;
		foreach ( array_merge( $busy, $blocked ) as $interval ) {
			if ( $reserved_start < $interval['end'] && $reserved_end > $interval['start'] ) {
				$conflict = true;
				break;
			}
		}
		if ( $conflict ) {
			continue;
		}
		$slots[] = array(
			'starts_at_local' => $candidate->setTimezone( $timezone )->format( 'Y-m-d H:i' ),
			'starts_at_utc' => $candidate->format( 'Y-m-d H:i:s' ),
			'ends_at_utc' => $service_end->format( 'Y-m-d H:i:s' ),
		);
	}
	return $slots;
}
