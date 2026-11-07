<?php
/**
 * Pure schedule normalization helpers.
 *
 * Kept independent of WordPress so input rules can be tested without a
 * database or a running WordPress installation.
 *
 * @package ParisanCore
 */

/**
 * Normalize a weekly schedule into safe salon-local wall-clock windows.
 *
 * Weekday keys follow WordPress: 0 Sunday through 6 Saturday.
 * A missing or invalid day is treated as unavailable, never as all-day open.
 *
 * @param mixed $input Untrusted schedule input.
 * @return array<int,array<string,mixed>>
 */
function parisan_core_normalize_weekly_schedule( $input ) {
	$input    = is_array( $input ) ? $input : array();
	$schedule = array();

	for ( $day = 0; $day <= 6; $day++ ) {
		$row   = isset( $input[ $day ] ) && is_array( $input[ $day ] ) ? $input[ $day ] : array();
		$off   = ! empty( $row['off'] );
		$start = isset( $row['start'] ) && is_string( $row['start'] ) ? $row['start'] : '';
		$end   = isset( $row['end'] ) && is_string( $row['end'] ) ? $row['end'] : '';
		$valid_time = static function ( $time ) {
			return 1 === preg_match( '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $time );
		};

		if ( $off ) {
			$schedule[ $day ] = array( 'off' => true );
		} elseif ( $valid_time( $start ) && $valid_time( $end ) && $end > $start ) {
			$schedule[ $day ] = array(
				'off'   => false,
				'start' => $start,
				'end'   => $end,
			);
		} else {
			$schedule[ $day ] = array( 'off' => true );
		}
	}

	return $schedule;
}
