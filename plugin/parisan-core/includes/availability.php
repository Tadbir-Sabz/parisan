<?php
/**
 * WordPress data adapter for salon-local availability calculation.
 *
 * @package ParisanCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Return the maximum number of days customers may book ahead.
 *
 * @return int
 */
function parisan_core_get_booking_horizon_days() {
	$value = get_option( 'parisan_booking_horizon_days', 90 );
	$value = is_scalar( $value ) ? (int) $value : 90;
	return min( 365, max( 1, $value ) );
}

/**
 * Filter candidate slots using the salon-wide lead-time and booking horizon.
 *
 * The same adapter is used by public and receptionist availability, and the
 * transactional booking writer reuses it while holding the staff lock.
 *
 * @param array                 $slots Candidate slots.
 * @param DateTimeImmutable|null $now_utc Optional UTC clock for deterministic tests.
 * @return array
 */
function parisan_core_filter_booking_window_slots( $slots, $now_utc = null ) {
	if ( ! is_array( $slots ) ) {
		return array();
	}
	$now_utc = $now_utc instanceof DateTimeImmutable
		? $now_utc->setTimezone( new DateTimeZone( 'UTC' ) )
		: new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
	$lead_minutes = get_option( 'parisan_booking_lead_time_minutes', 0 );
	$lead_minutes = is_scalar( $lead_minutes ) ? min( 10080, max( 0, (int) $lead_minutes ) ) : 0;
	$earliest = $now_utc->modify( '+' . $lead_minutes . ' minutes' );
	$horizon_local = $now_utc->setTimezone( wp_timezone() )->modify( '+' . parisan_core_get_booking_horizon_days() . ' days' );
	$latest_utc = $horizon_local->setTimezone( new DateTimeZone( 'UTC' ) );
	$filtered = array();
	foreach ( $slots as $slot ) {
		if ( ! is_array( $slot ) || empty( $slot['starts_at_utc'] ) || ! is_string( $slot['starts_at_utc'] ) ) {
			continue;
		}
		try {
			$start = new DateTimeImmutable( $slot['starts_at_utc'], new DateTimeZone( 'UTC' ) );
		} catch ( Exception $exception ) {
			continue;
		}
		if ( $start >= $earliest && $start <= $latest_utc ) {
			$filtered[] = $slot;
		}
	}
	return $filtered;
}

/**
 * Check whether a salon-local date falls inside the configured calendar horizon.
 *
 * @param string $date Local date in Y-m-d format.
 * @return bool
 */
function parisan_core_is_booking_date_within_horizon( $date ) {
	if ( ! is_string( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $date ) ) {
		return false;
	}
	$timezone = wp_timezone();
	$requested = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, $timezone );
	$errors = DateTimeImmutable::getLastErrors();
	if ( false === $requested || ( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) || $requested->format( 'Y-m-d' ) !== $date ) {
		return false;
	}
	$today = new DateTimeImmutable( 'today', $timezone );
	return $requested >= $today && $requested <= $today->modify( '+' . parisan_core_get_booking_horizon_days() . ' days' );
}

/**
 * Fetch available slots for a published service and eligible staff member.
 *
 * The calculation is read-only. It does not reserve a slot and must never be
 * treated as a concurrency guarantee; booking creation must re-check under a
 * database lock/transaction.
 *
 * @param int    $staff_id Staff post ID.
 * @param int    $service_id Service post ID.
 * @param string $date Salon-local date (Y-m-d).
 * @param int    $exclude_booking_id Optional booking row excluded for its own reschedule check.
 * @param int    $duration_override Optional duration snapshot in minutes.
 * @return array|WP_Error
 */
function parisan_core_get_available_slots( $staff_id, $service_id, $date, $exclude_booking_id = 0, $duration_override = 0 ) {
	global $wpdb;

	$staff_id   = absint( $staff_id );
	$service_id = absint( $service_id );

	if ( 'publish' !== get_post_status( $staff_id ) || 'parisan_staff' !== get_post_type( $staff_id ) || 'publish' !== get_post_status( $service_id ) || 'parisan_service' !== get_post_type( $service_id ) ) {
		return new WP_Error( 'parisan_invalid_booking_target', __( 'خدمت یا متخصص انتخاب‌شده معتبر نیست.', 'parisan-core' ) );
	}

	$eligible_services = get_post_meta( $staff_id, '_parisan_staff_services', true );
	$eligible_services = is_array( $eligible_services ) ? array_map( 'absint', $eligible_services ) : array();
	if ( ! in_array( $service_id, $eligible_services, true ) ) {
		return new WP_Error( 'parisan_service_not_available_for_staff', __( 'این خدمت توسط متخصص انتخاب‌شده ارائه نمی‌شود.', 'parisan-core' ) );
	}

	$duration = absint( $duration_override ) ? absint( $duration_override ) : absint( get_post_meta( $service_id, '_parisan_duration', true ) );
	if ( $duration < 1 || $duration > 1440 ) {
		return new WP_Error( 'parisan_invalid_service_duration', __( 'مدت زمان خدمت باید ابتدا در مدیریت تعریف شود.', 'parisan-core' ) );
	}

	$timezone = wp_timezone();
	$local_start = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $date, $timezone );
	$date_errors = DateTimeImmutable::getLastErrors();
	if ( false === $local_start || ( is_array( $date_errors ) && ( $date_errors['warning_count'] > 0 || $date_errors['error_count'] > 0 ) ) || $local_start->format( 'Y-m-d' ) !== $date ) {
		return new WP_Error( 'parisan_invalid_booking_date', __( 'تاریخ انتخاب‌شده معتبر نیست.', 'parisan-core' ) );
	}
	$local_end = $local_start->modify( '+1 day' );
	$utc = new DateTimeZone( 'UTC' );
	$range_start = $local_start->setTimezone( $utc )->format( 'Y-m-d H:i:s' );
	$range_end = $local_end->setTimezone( $utc )->format( 'Y-m-d H:i:s' );

	$bookings_table = $wpdb->prefix . 'parisan_bookings';
	$exceptions_table = $wpdb->prefix . 'parisan_schedule_exceptions';
	$busy_sql = "SELECT starts_at_utc, ends_at_utc FROM {$bookings_table}
		WHERE staff_id = %d AND status IN ('pending','confirmed')
		AND starts_at_utc < %s AND ends_at_utc > %s";
	$busy_args = array( $staff_id, $range_end, $range_start );
	$exclude_booking_id = absint( $exclude_booking_id );
	if ( $exclude_booking_id ) {
		$busy_sql .= ' AND id <> %d';
		$busy_args[] = $exclude_booking_id;
	}
	$busy_rows = $wpdb->get_results( $wpdb->prepare( $busy_sql, $busy_args ), ARRAY_A );
	$exception_rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT starts_at_utc, ends_at_utc FROM {$exceptions_table}
			WHERE staff_id = %d AND exception_type = 'unavailable'
			AND starts_at_utc < %s AND ends_at_utc > %s",
			$staff_id,
			$range_end,
			$range_start
		),
		ARRAY_A
	);

	$to_intervals = static function ( $rows ) {
		$intervals = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( isset( $row['starts_at_utc'], $row['ends_at_utc'] ) ) {
				$intervals[] = array( 'start' => $row['starts_at_utc'], 'end' => $row['ends_at_utc'] );
			}
		}
		return $intervals;
	};

	$weekly = get_post_meta( $staff_id, '_parisan_staff_weekly_schedule', true );
	$weekly = parisan_core_normalize_weekly_schedule( $weekly );
	$interval = absint( get_option( 'parisan_booking_slot_interval_minutes', 15 ) );
	$before = absint( get_option( 'parisan_booking_buffer_before_minutes', 0 ) );
	$after = absint( get_option( 'parisan_booking_buffer_after_minutes', 0 ) );

	$slots = parisan_core_calculate_available_slots(
		$date,
		$weekly,
		$timezone->getName(),
		$duration,
		max( 5, min( 120, $interval ) ),
		$to_intervals( $busy_rows ),
		$to_intervals( $exception_rows ),
		min( 240, $before ),
		min( 240, $after )
	);
	$slots = parisan_core_assign_resources_to_slots( $slots, $service_id, $range_start, $range_end, min( 240, $before ), min( 240, $after ) );
	return parisan_core_filter_booking_window_slots( $slots );
}
