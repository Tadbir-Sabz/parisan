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
		$break_start = isset( $row['break_start'] ) && is_string( $row['break_start'] ) ? $row['break_start'] : '';
		$break_end = isset( $row['break_end'] ) && is_string( $row['break_end'] ) ? $row['break_end'] : '';
		$valid_time = static function ( $time ) {
			return 1 === preg_match( '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $time );
		};
		$has_break = '' !== $break_start || '' !== $break_end;
		$valid_break = ! $has_break || ( $valid_time( $break_start ) && $valid_time( $break_end ) && $break_start < $break_end && $break_start >= $start && $break_end <= $end );
		if ( $off ) {
			$schedule[ $day ] = array( 'off' => true );
		} elseif ( $valid_time( $start ) && $valid_time( $end ) && $end > $start && $valid_break ) {
			$normalized = array( 'off' => false, 'start' => $start, 'end' => $end );
			if ( $has_break ) {
				$normalized['break_start'] = $break_start;
				$normalized['break_end'] = $break_end;
			}
			$schedule[ $day ] = $normalized;
		} else {
			$schedule[ $day ] = array( 'off' => true );
		}
	}
	return $schedule;
}

/**
 * Normalize one date-specific schedule override.
 *
 * A salon-wide override uses staff_id 0. An override either closes the date or
 * replaces the weekly wall-clock window for that date. Times are validated in
 * the salon timezone later by the availability adapter; this function only
 * enforces shape and ordering.
 *
 * @param mixed $input Untrusted override input.
 * @return array<string,mixed>|false
 */
function parisan_core_normalize_date_override( $input ) {
	$input = is_array( $input ) ? $input : array();
	$staff_id = isset( $input['staff_id'] ) && is_scalar( $input['staff_id'] ) ? max( 0, (int) $input['staff_id'] ) : 0;
	$date = isset( $input['override_date'] ) && is_string( $input['override_date'] ) ? trim( $input['override_date'] ) : '';
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $matches ) ) {
		return false;
	}
	$date_check = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, new DateTimeZone( 'UTC' ) );
	$date_errors = DateTimeImmutable::getLastErrors();
	if ( false === $date_check || ( is_array( $date_errors ) && ( $date_errors['warning_count'] > 0 || $date_errors['error_count'] > 0 ) ) || $date_check->format( 'Y-m-d' ) !== $date ) {
		return false;
	}

	$type = isset( $input['override_type'] ) && is_string( $input['override_type'] ) ? strtolower( preg_replace( '/[^a-z_]/', '', $input['override_type'] ) ) : '';
	$allowed_types = $staff_id ? array( 'closure', 'special_day', 'custom_hours' ) : array( 'holiday', 'special_day', 'custom_hours' );
	if ( ! in_array( $type, $allowed_types, true ) ) {
		$type = ! empty( $input['is_closed'] ) ? ( $staff_id ? 'closure' : 'holiday' ) : 'custom_hours';
	}
	$is_closed = ! empty( $input['is_closed'] ) || in_array( $type, array( 'holiday', 'closure' ), true );
	if ( $is_closed ) {
		$type = $staff_id ? 'closure' : 'holiday';
	}

	$valid_time = static function ( $time ) {
		return is_string( $time ) && 1 === preg_match( '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $time );
	};
	$start = $is_closed ? '' : ( isset( $input['start'] ) && is_string( $input['start'] ) ? $input['start'] : '' );
	$end = $is_closed ? '' : ( isset( $input['end'] ) && is_string( $input['end'] ) ? $input['end'] : '' );
	$break_start = $is_closed ? '' : ( isset( $input['break_start'] ) && is_string( $input['break_start'] ) ? $input['break_start'] : '' );
	$break_end = $is_closed ? '' : ( isset( $input['break_end'] ) && is_string( $input['break_end'] ) ? $input['break_end'] : '' );
	$has_break = '' !== $break_start || '' !== $break_end;
	if ( ! $is_closed ) {
		if ( ! $valid_time( $start ) || ! $valid_time( $end ) || $end <= $start ) {
			return false;
		}
		if ( $has_break && ( ! $valid_time( $break_start ) || ! $valid_time( $break_end ) || $break_end <= $break_start || $break_start < $start || $break_end > $end ) ) {
			return false;
		}
	}

	$note = isset( $input['note'] ) && is_string( $input['note'] ) ? trim( $input['note'] ) : '';
	if ( function_exists( 'mb_substr' ) ) {
		$note = mb_substr( $note, 0, 255, 'UTF-8' );
	} else {
		$note = substr( $note, 0, 255 );
	}

	return array(
		'staff_id'      => $staff_id,
		'override_date' => $date,
		'is_closed'     => $is_closed,
		'override_type' => $type,
		'start'         => $start,
		'end'           => $end,
		'break_start'   => $has_break && ! $is_closed ? $break_start : '',
		'break_end'     => $has_break && ! $is_closed ? $break_end : '',
		'note'          => $note,
	);
}

/**
 * Convert a normalized override into a weekly-schedule-compatible day row.
 *
 * @param array<string,mixed>|null $override Normalized override.
 * @return array<string,mixed>|null
 */
function parisan_core_date_override_to_schedule( $override ) {
	if ( ! is_array( $override ) || empty( $override['override_date'] ) ) {
		return null;
	}
	if ( ! empty( $override['is_closed'] ) ) {
		return array( 'off' => true );
	}
	if ( empty( $override['start'] ) || empty( $override['end'] ) || $override['end'] <= $override['start'] ) {
		return array( 'off' => true );
	}
	$row = array( 'off' => false, 'start' => $override['start'], 'end' => $override['end'] );
	if ( ! empty( $override['break_start'] ) && ! empty( $override['break_end'] ) ) {
		$row['break_start'] = $override['break_start'];
		$row['break_end'] = $override['break_end'];
	}
	return $row;
}

/**
 * Resolve the effective day schedule from weekly rules and date overrides.
 *
 * Precedence is deliberately safety-first:
 * 1. A salon-wide closure blocks every specialist.
 * 2. A specialist closure blocks only that specialist.
 * 3. A specialist date override replaces the weekly window.
 * 4. A salon-wide special day or custom-hours override applies to everyone.
 * 5. Otherwise the recurring weekly rule applies.
 *
 * @param string $date Salon-local Gregorian date.
 * @param array $weekly_schedule Normalized weekly schedule.
 * @param array<string,mixed>|null $staff_override Specialist override.
 * @param array<string,mixed>|null $salon_override Salon-wide override.
 * @return array<string,mixed>
 */
function parisan_core_resolve_effective_day_schedule( $date, $weekly_schedule, $staff_override = null, $salon_override = null ) {
	$weekly_schedule = is_array( $weekly_schedule ) ? $weekly_schedule : array();
	$timezone = new DateTimeZone( 'UTC' );
	$local = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $date, $timezone );
	$errors = DateTimeImmutable::getLastErrors();
	if ( false === $local || ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) || $local->format( 'Y-m-d' ) !== $date ) {
		return array( 'off' => true, 'source' => 'invalid_date' );
	}

	if ( is_array( $salon_override ) && ! empty( $salon_override['is_closed'] ) ) {
		return array( 'off' => true, 'source' => 'salon_closure' );
	}
	if ( is_array( $staff_override ) && ! empty( $staff_override['is_closed'] ) ) {
		return array( 'off' => true, 'source' => 'staff_closure' );
	}
	$staff_schedule = parisan_core_date_override_to_schedule( $staff_override );
	if ( null !== $staff_schedule ) {
		$staff_schedule['source'] = 'staff_override';
		return $staff_schedule;
	}
	$salon_schedule = parisan_core_date_override_to_schedule( $salon_override );
	if ( null !== $salon_schedule ) {
		$salon_schedule['source'] = ! empty( $salon_override['override_type'] ) && 'special_day' === $salon_override['override_type'] ? 'salon_special_day' : 'salon_override';
		return $salon_schedule;
	}

	$day = (int) $local->format( 'w' );
	$hours = isset( $weekly_schedule[ $day ] ) && is_array( $weekly_schedule[ $day ] ) ? $weekly_schedule[ $day ] : array( 'off' => true );
	$hours['source'] = empty( $hours['off'] ) ? 'weekly' : 'weekly_closed';
	return $hours;
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
 * @param array<string,mixed>|null $staff_override Date-specific specialist override.
 * @param array<string,mixed>|null $salon_override Date-specific salon override.
 * @return array<int,array<string,string>>
 */
function parisan_core_calculate_available_slots( $date, $weekly_schedule, $timezone_name, $duration_minutes, $slot_interval_minutes, $busy_intervals = array(), $exceptions = array(), $buffer_before_minutes = 0, $buffer_after_minutes = 0, $staff_override = null, $salon_override = null ) {
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
	$hours = parisan_core_resolve_effective_day_schedule( $date, $weekly_schedule, $staff_override, $salon_override );
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
	if ( ! empty( $hours['break_start'] ) || ! empty( $hours['break_end'] ) ) {
		$break_start = parisan_core_local_datetime_to_utc( $date, isset( $hours['break_start'] ) ? $hours['break_start'] : '', $timezone );
		$break_end = parisan_core_local_datetime_to_utc( $date, isset( $hours['break_end'] ) ? $hours['break_end'] : '', $timezone );
		if ( false === $break_start || false === $break_end || $break_end <= $break_start ) {
			return array();
		}
		$blocked[] = array( 'start' => $break_start, 'end' => $break_end );
	}
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
