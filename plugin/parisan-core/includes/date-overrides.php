<?php
/**
 * Date-specific salon and specialist schedule overrides.
 *
 * Weekly rules remain the recurring source of truth. This adapter stores
 * exceptions to that recurrence: salon holidays, specialist leave, special
 * working days, and custom opening hours for one local calendar date.
 *
 * @package ParisanCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Map one database row to the normalized override shape.
 *
 * @param array<string,mixed> $row Database row.
 * @return array<string,mixed>
 */
function parisan_core_map_date_override_row( $row ) {
	$row = is_array( $row ) ? $row : array();
	return array(
		'id'            => isset( $row['id'] ) ? (int) $row['id'] : 0,
		'staff_id'      => isset( $row['staff_id'] ) ? (int) $row['staff_id'] : 0,
		'override_date' => isset( $row['override_date'] ) ? (string) $row['override_date'] : '',
		'override_type' => isset( $row['override_type'] ) ? (string) $row['override_type'] : 'custom_hours',
		'is_closed'     => ! empty( $row['is_closed'] ),
		'start'         => isset( $row['start_time'] ) ? (string) $row['start_time'] : '',
		'end'           => isset( $row['end_time'] ) ? (string) $row['end_time'] : '',
		'break_start'   => isset( $row['break_start_time'] ) ? (string) $row['break_start_time'] : '',
		'break_end'     => isset( $row['break_end_time'] ) ? (string) $row['break_end_time'] : '',
		'note'          => isset( $row['note'] ) ? (string) $row['note'] : '',
	);
}

/**
 * Validate a Gregorian date string used by override storage.
 *
 * @param mixed $date Candidate date.
 * @return bool
 */
function parisan_core_is_valid_local_date( $date ) {
	if ( ! is_string( $date ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $matches ) ) {
		return false;
	}
	$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, new DateTimeZone( 'UTC' ) );
	$errors = DateTimeImmutable::getLastErrors();
	return false !== $parsed && ! ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) && $parsed->format( 'Y-m-d' ) === $date;
}

/**
 * Fetch one staff or salon override for a local date.
 *
 * @param int    $staff_id Specialist post ID; 0 means salon-wide.
 * @param string $date Local Gregorian date.
 * @return array<string,mixed>|null
 */
function parisan_core_get_date_override( $staff_id, $date ) {
	global $wpdb;

	$staff_id = max( 0, absint( $staff_id ) );
	if ( ! parisan_core_is_valid_local_date( $date ) ) {
		return null;
	}
	$table = $wpdb->prefix . 'parisan_schedule_date_overrides';
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, staff_id, override_date, override_type, is_closed, start_time, end_time, break_start_time, break_end_time, note FROM {$table} WHERE staff_id = %d AND override_date = %s LIMIT 1",
			$staff_id,
			$date
		),
		ARRAY_A
	);
	return is_array( $row ) ? parisan_core_map_date_override_row( $row ) : null;
}

/**
 * Fetch overrides in an inclusive local date range.
 *
 * The salon scope (staff_id 0) is always included. Pass null to include every
 * specialist, or a list of staff IDs to limit specialist rows.
 *
 * @param string   $start_date Inclusive local start date.
 * @param string   $end_date Inclusive local end date.
 * @param int[]|null $staff_ids Specialist filter; null means all.
 * @return array<string,array<string,mixed>> Keyed by "staff_id:date".
 */
function parisan_core_get_date_overrides_for_range( $start_date, $end_date, $staff_ids = null ) {
	global $wpdb;

	if ( ! parisan_core_is_valid_local_date( $start_date ) || ! parisan_core_is_valid_local_date( $end_date ) || $end_date < $start_date ) {
		return array();
	}
	$table = $wpdb->prefix . 'parisan_schedule_date_overrides';
	$where = 'override_date >= %s AND override_date <= %s';
	$args = array( $start_date, $end_date );
	if ( null !== $staff_ids ) {
		$staff_ids = is_array( $staff_ids ) ? array_values( array_unique( array_map( 'absint', $staff_ids ) ) ) : array();
		$staff_ids[] = 0;
		$staff_ids = array_values( array_unique( $staff_ids ) );
		$placeholders = implode( ',', array_fill( 0, count( $staff_ids ), '%d' ) );
		$where .= " AND staff_id IN ({$placeholders})";
		$args = array_merge( $args, $staff_ids );
	}
	$sql = "SELECT id, staff_id, override_date, override_type, is_closed, start_time, end_time, break_start_time, break_end_time, note FROM {$table} WHERE {$where} ORDER BY override_date ASC, staff_id ASC LIMIT 10000";
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
	$map = array();
	foreach ( is_array( $rows ) ? $rows : array() as $row ) {
		$override = parisan_core_map_date_override_row( $row );
		$map[ $override['staff_id'] . ':' . $override['override_date'] ] = $override;
	}
	return $map;
}

/**
 * Count existing pending/confirmed bookings on a local date.
 *
 * @param string $date Local date.
 * @param int[]  $staff_ids Optional staff filter.
 * @return array<string,mixed> Total and per-staff counts.
 */
function parisan_core_get_day_booking_summary( $date, $staff_ids = array() ) {
	global $wpdb;

	$summary = array( 'total' => 0, 'by_staff' => array() );
	if ( ! parisan_core_is_valid_local_date( $date ) ) {
		return $summary;
	}
	$timezone = wp_timezone();
	$start = parisan_core_local_datetime_to_utc( $date, '00:00', $timezone );
	$next_date = ( new DateTimeImmutable( $date . ' 12:00:00', $timezone ) )->modify( '+1 day' )->format( 'Y-m-d' );
	$end = parisan_core_local_datetime_to_utc( $next_date, '00:00', $timezone );
	if ( false === $start || false === $end ) {
		return $summary;
	}
	$table = $wpdb->prefix . 'parisan_bookings';
	$sql = "SELECT staff_id, COUNT(*) AS booking_count FROM {$table} WHERE status IN ('pending','confirmed') AND starts_at_utc < %s AND ends_at_utc > %s";
	$args = array( $end->format( 'Y-m-d H:i:s' ), $start->format( 'Y-m-d H:i:s' ) );
	$staff_ids = is_array( $staff_ids ) ? array_values( array_unique( array_map( 'absint', $staff_ids ) ) ) : array();
	if ( $staff_ids ) {
		$placeholders = implode( ',', array_fill( 0, count( $staff_ids ), '%d' ) );
		$sql .= " AND staff_id IN ({$placeholders})";
		$args = array_merge( $args, $staff_ids );
	}
	$sql .= ' GROUP BY staff_id';
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
	foreach ( is_array( $rows ) ? $rows : array() as $row ) {
		$count = (int) $row['booking_count'];
		$summary['by_staff'][ (int) $row['staff_id'] ] = $count;
		$summary['total'] += $count;
	}
	return $summary;
}

/**
 * Count staff exception intervals overlapping a local date.
 *
 * @param string $date Local date.
 * @param int[]  $staff_ids Optional staff filter.
 * @return int
 */
function parisan_core_get_day_exception_count( $date, $staff_ids = array() ) {
	global $wpdb;

	if ( ! parisan_core_is_valid_local_date( $date ) ) {
		return 0;
	}
	$timezone = wp_timezone();
	$start = parisan_core_local_datetime_to_utc( $date, '00:00', $timezone );
	$next_date = ( new DateTimeImmutable( $date . ' 12:00:00', $timezone ) )->modify( '+1 day' )->format( 'Y-m-d' );
	$end = parisan_core_local_datetime_to_utc( $next_date, '00:00', $timezone );
	if ( false === $start || false === $end ) {
		return 0;
	}
	$table = $wpdb->prefix . 'parisan_schedule_exceptions';
	$sql = "SELECT COUNT(*) FROM {$table} WHERE exception_type = 'unavailable' AND starts_at_utc < %s AND ends_at_utc > %s";
	$args = array( $end->format( 'Y-m-d H:i:s' ), $start->format( 'Y-m-d H:i:s' ) );
	$staff_ids = is_array( $staff_ids ) ? array_values( array_unique( array_map( 'absint', $staff_ids ) ) ) : array();
	if ( $staff_ids ) {
		$placeholders = implode( ',', array_fill( 0, count( $staff_ids ), '%d' ) );
		$sql .= " AND staff_id IN ({$placeholders})";
		$args = array_merge( $args, $staff_ids );
	}
	return (int) $wpdb->get_var( $wpdb->prepare( $sql, $args ) );
}

/**
 * Resolve one specialist's effective schedule for one local date.
 *
 * @param int    $staff_id Specialist post ID.
 * @param string $date Local Gregorian date.
 * @return array<string,mixed>
 */
function parisan_core_get_staff_effective_day_schedule( $staff_id, $date ) {
	$staff_id = absint( $staff_id );
	$weekly = parisan_core_normalize_weekly_schedule( get_post_meta( $staff_id, '_parisan_staff_weekly_schedule', true ) );
	return parisan_core_resolve_effective_day_schedule(
		$date,
		$weekly,
		parisan_core_get_date_override( $staff_id, $date ),
		parisan_core_get_date_override( 0, $date )
	);
}

/**
 * Save or update a date override with a consistent calendar lock order.
 *
 * @param array $input Untrusted or already sanitized override input.
 * @param int   $override_id Optional existing row ID.
 * @return array|WP_Error Row ID and existing booking count, or a safe error.
 */
function parisan_core_save_date_override( $input, $override_id = 0 ) {
	global $wpdb;

	$input = is_array( $input ) ? $input : array();
	if ( isset( $input['note'] ) && is_string( $input['note'] ) ) {
		$input['note'] = sanitize_text_field( $input['note'] );
	}
	$normalized = parisan_core_normalize_date_override( $input );
	if ( false === $normalized ) {
		return new WP_Error( 'parisan_invalid_date_override', __( 'تاریخ یا ساعت برنامهٔ ویژه معتبر نیست.', 'parisan-core' ) );
	}
	$staff_id = $normalized['staff_id'];
	if ( $staff_id && ( 'parisan_staff' !== get_post_type( $staff_id ) || 'publish' !== get_post_status( $staff_id ) ) ) {
		return new WP_Error( 'parisan_invalid_override_staff', __( 'متخصص انتخاب‌شده معتبر یا منتشرشده نیست.', 'parisan-core' ) );
	}

	$table = $wpdb->prefix . 'parisan_schedule_date_overrides';
	$override_id = absint( $override_id );
	$salon_lock = parisan_core_acquire_salon_calendar_lock();
	if ( is_wp_error( $salon_lock ) ) {
		return new WP_Error( 'parisan_calendar_lock_unavailable', __( 'تقویم سالن در حال تغییر است؛ لطفاً دوباره تلاش کنید.', 'parisan-core' ) );
	}
	$staff_lock = $staff_id ? parisan_core_acquire_staff_schedule_lock( $staff_id ) : '';
	if ( is_wp_error( $staff_lock ) ) {
		parisan_core_release_salon_calendar_lock( $salon_lock );
		return new WP_Error( 'parisan_calendar_lock_unavailable', __( 'تقویم متخصص در حال تغییر است؛ لطفاً دوباره تلاش کنید.', 'parisan-core' ) );
	}

	try {
		if ( $override_id ) {
			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d LIMIT 1", $override_id ) );
			if ( null === $existing ) {
				return new WP_Error( 'parisan_date_override_not_found', __( 'برنامهٔ ویژه پیدا نشد.', 'parisan-core' ) );
			}
		} else {
			$override_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE staff_id = %d AND override_date = %s LIMIT 1",
					$staff_id,
					$normalized['override_date']
				)
			);
		}

		$now = current_time( 'mysql', true );
		$data = array(
			'staff_id'         => $staff_id,
			'override_date'    => $normalized['override_date'],
			'override_type'    => $normalized['override_type'],
			'is_closed'        => $normalized['is_closed'] ? 1 : 0,
			'start_time'       => $normalized['start'],
			'end_time'         => $normalized['end'],
			'break_start_time' => $normalized['break_start'],
			'break_end_time'   => $normalized['break_end'],
			'note'             => $normalized['note'],
			'updated_at_utc'   => $now,
		);
		$formats = array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' );
		if ( $override_id ) {
			$saved = $wpdb->update( $table, $data, array( 'id' => $override_id ), $formats, array( '%d' ) );
			if ( false === $saved ) {
				return new WP_Error( 'parisan_date_override_save_failed', __( 'ذخیره برنامهٔ ویژه انجام نشد.', 'parisan-core' ) );
			}
		} else {
			$data['created_at_utc'] = $now;
			$formats[] = '%s';
			$saved = $wpdb->insert( $table, $data, $formats );
			if ( false === $saved ) {
				return new WP_Error( 'parisan_date_override_save_failed', __( 'ذخیره برنامهٔ ویژه انجام نشد.', 'parisan-core' ) );
			}
			$override_id = (int) $wpdb->insert_id;
		}
	} finally {
		if ( $staff_lock ) {
			parisan_core_release_staff_schedule_lock( $staff_lock );
		}
		parisan_core_release_salon_calendar_lock( $salon_lock );
	}

	$summary = parisan_core_get_day_booking_summary( $normalized['override_date'], $staff_id ? array( $staff_id ) : array() );
	return array( 'id' => $override_id, 'bookings' => (int) $summary['total'] );
}

/**
 * Delete a date override under the same lock order as booking creation.
 *
 * @param int $override_id Override row ID.
 * @return true|WP_Error
 */
function parisan_core_delete_date_override( $override_id ) {
	global $wpdb;

	$override_id = absint( $override_id );
	if ( ! $override_id ) {
		return new WP_Error( 'parisan_invalid_date_override', __( 'برنامهٔ ویژه معتبر نیست.', 'parisan-core' ) );
	}
	$table = $wpdb->prefix . 'parisan_schedule_date_overrides';
	$staff_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT staff_id FROM {$table} WHERE id = %d LIMIT 1", $override_id ) );
	if ( null === $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d LIMIT 1", $override_id ) ) ) {
		return new WP_Error( 'parisan_date_override_not_found', __( 'برنامهٔ ویژه پیدا نشد.', 'parisan-core' ) );
	}
	$salon_lock = parisan_core_acquire_salon_calendar_lock();
	if ( is_wp_error( $salon_lock ) ) {
		return new WP_Error( 'parisan_calendar_lock_unavailable', __( 'تقویم سالن در حال تغییر است؛ لطفاً دوباره تلاش کنید.', 'parisan-core' ) );
	}
	$staff_lock = $staff_id ? parisan_core_acquire_staff_schedule_lock( $staff_id ) : '';
	if ( is_wp_error( $staff_lock ) ) {
		parisan_core_release_salon_calendar_lock( $salon_lock );
		return new WP_Error( 'parisan_calendar_lock_unavailable', __( 'تقویم متخصص در حال تغییر است؛ لطفاً دوباره تلاش کنید.', 'parisan-core' ) );
	}
	try {
		$deleted = $wpdb->delete( $table, array( 'id' => $override_id ), array( '%d' ) );
	} finally {
		if ( $staff_lock ) {
			parisan_core_release_staff_schedule_lock( $staff_lock );
		}
		parisan_core_release_salon_calendar_lock( $salon_lock );
	}
	return false === $deleted ? new WP_Error( 'parisan_date_override_delete_failed', __( 'حذف برنامهٔ ویژه انجام نشد.', 'parisan-core' ) ) : true;
}
