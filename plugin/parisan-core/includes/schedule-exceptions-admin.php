<?php
/**
 * Persian RTL calendar administration for availability, holidays, and
 * date-specific schedule overrides.
 *
 * All stored boundaries remain UTC in the transactional tables. This screen
 * accepts Jalali form dates, converts them once to canonical Gregorian dates,
 * and renders every label in the site's configured timezone.
 *
 * @package ParisanCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Load calendar assets only on the calendar screen.
 *
 * @param string $hook_suffix Current admin hook.
 * @return void
 */
function parisan_core_enqueue_calendar_admin_assets( $hook_suffix ) {
	if ( false === strpos( (string) $hook_suffix, 'parisan-schedule-exceptions' ) ) {
		return;
	}
	wp_enqueue_style( 'parisan-calendar-admin', PARISAN_CORE_URL . 'assets/css/calendar-admin.css', array(), PARISAN_CORE_VERSION );
	wp_enqueue_script( 'parisan-calendar-admin', PARISAN_CORE_URL . 'assets/js/calendar-admin.js', array(), PARISAN_CORE_VERSION, true );
}
add_action( 'admin_enqueue_scripts', 'parisan_core_enqueue_calendar_admin_assets' );

/**
 * Build a URL for the calendar screen.
 *
 * @param array<string,mixed> $args Query arguments.
 * @return string
 */
function parisan_core_calendar_admin_url( $args = array() ) {
	return add_query_arg( is_array( $args ) ? $args : array(), admin_url( 'edit.php?post_type=parisan_staff&page=parisan-schedule-exceptions' ) );
}

/**
 * Parse either a Jalali form date or an explicit Gregorian Y-m-d value.
 *
 * Gregorian years before 1900 are treated as Jalali to keep the old native
 * input compatible while the primary form is Persian/Jalali.
 *
 * @param mixed $value Untrusted date input.
 * @return string|false Canonical Gregorian Y-m-d.
 */
function parisan_core_calendar_parse_admin_date( $value ) {
	if ( ! is_scalar( $value ) ) {
		return false;
	}
	$value = trim( parisan_core_to_latin_digits( $value ) );
	if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $matches ) && (int) $matches[1] >= 1900 ) {
		$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, new DateTimeZone( 'UTC' ) );
		$errors = DateTimeImmutable::getLastErrors();
		return false !== $parsed && ! ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) && $parsed->format( 'Y-m-d' ) === $value ? $value : false;
	}
	return parisan_core_parse_jalali_date_input( $value );
}

/**
 * Translate a resolved schedule source for administrators.
 *
 * @param string $source Source key.
 * @return string
 */
function parisan_core_calendar_source_label( $source ) {
	$labels = array(
		'weekly'          => __( 'قاعده هفتگی', 'parisan-core' ),
		'weekly_closed'   => __( 'تعطیل هفتگی', 'parisan-core' ),
		'staff_override'  => __( 'برنامه ویژه متخصص', 'parisan-core' ),
		'staff_closure'   => __( 'مرخصی متخصص', 'parisan-core' ),
		'salon_closure'   => __( 'تعطیلی سالن', 'parisan-core' ),
		'salon_special_day' => __( 'روز کاری ویژه سالن', 'parisan-core' ),
		'salon_override'  => __( 'برنامه ویژه سالن', 'parisan-core' ),
		'invalid_date'    => __( 'تاریخ نامعتبر', 'parisan-core' ),
	);
	return isset( $labels[ $source ] ) ? $labels[ $source ] : (string) $source;
}

/**
 * Return a working-time label for a resolved schedule row.
 *
 * @param array<string,mixed> $schedule Resolved schedule.
 * @return string
 */
function parisan_core_calendar_hours_label( $schedule ) {
	if ( ! empty( $schedule['off'] ) || empty( $schedule['start'] ) || empty( $schedule['end'] ) ) {
		return __( 'تعطیل', 'parisan-core' );
	}
	$label = parisan_core_to_persian_digits( $schedule['start'] ) . ' تا ' . parisan_core_to_persian_digits( $schedule['end'] );
	if ( ! empty( $schedule['break_start'] ) && ! empty( $schedule['break_end'] ) ) {
		$label .= ' (' . __( 'استراحت', 'parisan-core' ) . ' ' . parisan_core_to_persian_digits( $schedule['break_start'] ) . '–' . parisan_core_to_persian_digits( $schedule['break_end'] ) . ')';
	}
	return $label;
}

/**
 * Fetch weekly schedules for the visible staff list once.
 *
 * @param WP_Post[] $staff Staff posts.
 * @return array<int,array<int,array<string,mixed>>>
 */
function parisan_core_calendar_weekly_map( $staff ) {
	$map = array();
	foreach ( is_array( $staff ) ? $staff : array() as $person ) {
		$map[ (int) $person->ID ] = parisan_core_normalize_weekly_schedule( get_post_meta( $person->ID, '_parisan_staff_weekly_schedule', true ) );
	}
	return $map;
}

/**
 * Aggregate booking and partial-exception activity for a local date range.
 *
 * @param string $start_date First local date.
 * @param string $end_date Last local date.
 * @param int[]  $staff_ids Visible staff.
 * @return array<string,mixed>
 */
function parisan_core_calendar_activity_buckets( $start_date, $end_date, $staff_ids ) {
	global $wpdb;

	$result = array( 'bookings' => array(), 'booking_staff' => array(), 'exceptions' => array(), 'exception_staff' => array() );
	if ( ! parisan_core_is_valid_local_date( $start_date ) || ! parisan_core_is_valid_local_date( $end_date ) || $end_date < $start_date ) {
		return $result;
	}
	$timezone = wp_timezone();
	$start = parisan_core_local_datetime_to_utc( $start_date, '00:00', $timezone );
	$after_last = ( new DateTimeImmutable( $end_date . ' 12:00:00', $timezone ) )->modify( '+1 day' )->format( 'Y-m-d' );
	$end = parisan_core_local_datetime_to_utc( $after_last, '00:00', $timezone );
	if ( false === $start || false === $end ) {
		return $result;
	}
	$start_sql = $start->format( 'Y-m-d H:i:s' );
	$end_sql = $end->format( 'Y-m-d H:i:s' );
	$staff_ids = is_array( $staff_ids ) ? array_values( array_unique( array_map( 'absint', $staff_ids ) ) ) : array();
	if ( ! $staff_ids ) {
		return $result;
	}
	$placeholders = implode( ',', array_fill( 0, count( $staff_ids ), '%d' ) );

	$bookings_table = $wpdb->prefix . 'parisan_bookings';
	$booking_sql = "SELECT staff_id, starts_at_utc FROM {$bookings_table} WHERE status IN ('pending','confirmed') AND starts_at_utc >= %s AND starts_at_utc < %s";
	$booking_args = array( $start_sql, $end_sql );
	if ( $placeholders ) {
		$booking_sql .= " AND staff_id IN ({$placeholders})";
		$booking_args = array_merge( $booking_args, $staff_ids );
	}
	$booking_rows = $wpdb->get_results( $wpdb->prepare( $booking_sql, $booking_args ), ARRAY_A );
	foreach ( is_array( $booking_rows ) ? $booking_rows : array() as $row ) {
		try {
			$local_date = ( new DateTimeImmutable( $row['starts_at_utc'], new DateTimeZone( 'UTC' ) ) )->setTimezone( $timezone )->format( 'Y-m-d' );
		} catch ( Exception $exception ) {
			continue;
		}
		$result['bookings'][ $local_date ] = isset( $result['bookings'][ $local_date ] ) ? $result['bookings'][ $local_date ] + 1 : 1;
		$sid = (int) $row['staff_id'];
		if ( ! isset( $result['booking_staff'][ $local_date ] ) ) {
			$result['booking_staff'][ $local_date ] = array();
		}
		$result['booking_staff'][ $local_date ][ $sid ] = isset( $result['booking_staff'][ $local_date ][ $sid ] ) ? $result['booking_staff'][ $local_date ][ $sid ] + 1 : 1;
	}

	$exceptions_table = $wpdb->prefix . 'parisan_schedule_exceptions';
	$exception_sql = "SELECT staff_id, starts_at_utc, ends_at_utc FROM {$exceptions_table} WHERE exception_type = 'unavailable' AND starts_at_utc < %s AND ends_at_utc > %s";
	$exception_args = array( $end_sql, $start_sql );
	if ( $placeholders ) {
		$exception_sql .= " AND staff_id IN ({$placeholders})";
		$exception_args = array_merge( $exception_args, $staff_ids );
	}
	$exception_rows = $wpdb->get_results( $wpdb->prepare( $exception_sql, $exception_args ), ARRAY_A );
	foreach ( is_array( $exception_rows ) ? $exception_rows : array() as $row ) {
		try {
			$local_start = ( new DateTimeImmutable( $row['starts_at_utc'], new DateTimeZone( 'UTC' ) ) )->setTimezone( $timezone );
			$local_end = ( new DateTimeImmutable( $row['ends_at_utc'], new DateTimeZone( 'UTC' ) ) )->setTimezone( $timezone )->modify( '-1 second' );
		} catch ( Exception $exception ) {
			continue;
		}
		$cursor = $local_start->setTime( 0, 0 );
		$last = $local_end->setTime( 0, 0 );
		while ( $cursor <= $last ) {
			$local_date = $cursor->format( 'Y-m-d' );
			$sid = (int) $row['staff_id'];
			if ( ! isset( $result['exception_staff'][ $local_date ] ) ) {
				$result['exception_staff'][ $local_date ] = array();
			}
			$result['exception_staff'][ $local_date ][ $sid ] = isset( $result['exception_staff'][ $local_date ][ $sid ] ) ? $result['exception_staff'][ $local_date ][ $sid ] + 1 : 1;
			$result['exceptions'][ $local_date ] = isset( $result['exceptions'][ $local_date ] ) ? $result['exceptions'][ $local_date ] + 1 : 1;
			$cursor = $cursor->modify( '+1 day' );
			if ( $cursor->format( 'Y-m-d' ) > $end_date ) {
				break;
			}
		}
	}
	return $result;
}

/**
 * Resolve the aggregate state of one date for the selected staff.
 *
 * @param string $date Local date.
 * @param WP_Post[] $staff Visible staff.
 * @param array $weekly_map Weekly schedules keyed by staff ID.
 * @param array $overrides Override map keyed by "staff_id:date".
 * @return array<string,mixed>
 */
function parisan_core_calendar_day_state( $date, $staff, $weekly_map, $overrides ) {
	$salon_override = isset( $overrides[ '0:' . $date ] ) ? $overrides[ '0:' . $date ] : null;
	$state = array( 'key' => 'closed', 'label' => __( 'تعطیل', 'parisan-core' ), 'open_count' => 0, 'staff_count' => 0, 'salon_override' => $salon_override );
	if ( is_array( $salon_override ) && ! empty( $salon_override['is_closed'] ) ) {
		$state['key'] = 'holiday';
		$state['label'] = __( 'تعطیلی سالن', 'parisan-core' );
		return $state;
	}
	foreach ( is_array( $staff ) as $person ) {
		$staff_id = (int) $person->ID;
		++$state['staff_count'];
		$staff_override = isset( $overrides[ $staff_id . ':' . $date ] ) ? $overrides[ $staff_id . ':' . $date ] : null;
		$resolved = parisan_core_resolve_effective_day_schedule(
			$date,
			isset( $weekly_map[ $staff_id ] ) ? $weekly_map[ $staff_id ] : array(),
			$staff_override,
			$salon_override
		);
		if ( empty( $resolved['off'] ) ) {
			++$state['open_count'];
		}
	}
	if ( $state['open_count'] > 0 ) {
		$state['key'] = is_array( $salon_override ) && empty( $salon_override['is_closed'] ) ? 'special' : 'open';
		$state['label'] = 'special' === $state['key'] ? __( 'روز کاری ویژه', 'parisan-core' ) : __( 'باز', 'parisan-core' );
	}
	return $state;
}

/**
 * Handle creation of a partial-day staff exception.
 *
 * @return void
 */
function parisan_core_handle_schedule_exception_create() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'اجازه انجام این عملیات را ندارید.', 'parisan-core' ), 403 );
	}
	check_admin_referer( 'parisan_create_schedule_exception', 'parisan_exception_nonce' );

	$staff_id = isset( $_POST['staff_id'] ) && is_scalar( $_POST['staff_id'] ) ? absint( wp_unslash( $_POST['staff_id'] ) ) : 0;
	if ( ! $staff_id || 'parisan_staff' !== get_post_type( $staff_id ) || 'publish' !== get_post_status( $staff_id ) || ! current_user_can( 'edit_post', $staff_id ) ) {
		wp_die( esc_html__( 'متخصص انتخاب‌شده معتبر نیست یا دسترسی ویرایش آن را ندارید.', 'parisan-core' ), 403 );
	}

	$date = parisan_core_calendar_parse_admin_date( isset( $_POST['exception_date'] ) ? wp_unslash( $_POST['exception_date'] ) : '' );
	$all_day = isset( $_POST['all_day'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['all_day'] ) );
	$start_time = $all_day ? '00:00' : ( isset( $_POST['start_time'] ) && is_string( $_POST['start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['start_time'] ) ) : '' );
	$end_time = $all_day ? '00:00' : ( isset( $_POST['end_time'] ) && is_string( $_POST['end_time'] ) ? sanitize_text_field( wp_unslash( $_POST['end_time'] ) ) : '' );
	if ( false === $date ) {
		wp_die( esc_html__( 'تاریخ واردشده معتبر نیست. تاریخ را به شکل شمسی مانند ۱۴۰۵/۰۸/۲۲ وارد کنید.', 'parisan-core' ), 400 );
	}

	$timezone = wp_timezone();
	$end_date = $all_day ? ( new DateTimeImmutable( $date . ' 12:00:00', $timezone ) )->modify( '+1 day' )->format( 'Y-m-d' ) : $date;
	$interval = parisan_core_local_interval_to_utc( $date, $start_time, $end_date, $end_time, $timezone );
	if ( false === $interval ) {
		wp_die( esc_html__( 'بازه زمانی معتبر نیست. ساعت پایان باید بعد از شروع باشد و زمان محلی باید وجود داشته باشد.', 'parisan-core' ), 400 );
	}

	$note = isset( $_POST['note'] ) && is_string( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '';
	if ( function_exists( 'mb_substr' ) ) {
		$note = mb_substr( $note, 0, 255 );
	} else {
		$note = substr( $note, 0, 255 );
	}

	global $wpdb;
	$table = $wpdb->prefix . 'parisan_schedule_exceptions';
	$lock_name = parisan_core_acquire_staff_schedule_lock( $staff_id );
	if ( is_wp_error( $lock_name ) ) {
		wp_die( esc_html__( 'تقویم متخصص در حال تغییر است؛ لطفاً دوباره تلاش کنید.', 'parisan-core' ), 409 );
	}
	$inserted = false;
	$conflicts = 0;
	try {
		$now = current_time( 'mysql', true );
		$inserted = $wpdb->insert(
			$table,
			array(
				'staff_id' => $staff_id,
				'starts_at_utc' => $interval['start']->format( 'Y-m-d H:i:s' ),
				'ends_at_utc' => $interval['end']->format( 'Y-m-d H:i:s' ),
				'exception_type' => 'unavailable',
				'note' => $note,
				'created_at_utc' => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( false !== $inserted ) {
			$bookings_table = $wpdb->prefix . 'parisan_bookings';
			$conflicts = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$bookings_table} WHERE staff_id = %d AND status IN ('pending','confirmed') AND starts_at_utc < %s AND ends_at_utc > %s",
					$staff_id,
					$interval['end']->format( 'Y-m-d H:i:s' ),
					$interval['start']->format( 'Y-m-d H:i:s' )
				)
			);
		}
	} finally {
		parisan_core_release_staff_schedule_lock( $lock_name );
	}
	if ( false === $inserted ) {
		wp_die( esc_html__( 'ذخیره استثنای تقویم انجام نشد.', 'parisan-core' ), 500 );
	}
	$redirect = parisan_core_calendar_admin_url(
		array(
			'view' => 'day',
			'date' => $date,
			'exception_saved' => 1,
			'exception_conflicts' => $conflicts,
		)
	);
	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_post_parisan_create_schedule_exception', 'parisan_core_handle_schedule_exception_create' );

/**
 * Handle deletion of a single partial-day exception.
 *
 * @return void
 */
function parisan_core_handle_schedule_exception_delete() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'اجازه انجام این عملیات را ندارید.', 'parisan-core' ), 403 );
	}
	$exception_id = isset( $_POST['exception_id'] ) && is_scalar( $_POST['exception_id'] ) ? absint( wp_unslash( $_POST['exception_id'] ) ) : 0;
	$staff_id = isset( $_POST['staff_id'] ) && is_scalar( $_POST['staff_id'] ) ? absint( wp_unslash( $_POST['staff_id'] ) ) : 0;
	check_admin_referer( 'parisan_delete_schedule_exception_' . $exception_id, 'parisan_exception_nonce' );

	if ( ! $exception_id || ! $staff_id || 'parisan_staff' !== get_post_type( $staff_id ) || ! current_user_can( 'edit_post', $staff_id ) ) {
		wp_die( esc_html__( 'استثنا یا متخصص معتبر نیست.', 'parisan-core' ), 403 );
	}

	global $wpdb;
	$table = $wpdb->prefix . 'parisan_schedule_exceptions';
	$lock_name = parisan_core_acquire_staff_schedule_lock( $staff_id );
	if ( is_wp_error( $lock_name ) ) {
		wp_die( esc_html__( 'تقویم متخصص در حال تغییر است؛ لطفاً دوباره تلاش کنید.', 'parisan-core' ), 409 );
	}
	$deleted = false;
	try {
		$deleted = $wpdb->delete( $table, array( 'id' => $exception_id, 'staff_id' => $staff_id ), array( '%d', '%d' ) );
	} finally {
		parisan_core_release_staff_schedule_lock( $lock_name );
	}
	if ( false === $deleted ) {
		wp_die( esc_html__( 'حذف استثنا انجام نشد.', 'parisan-core' ), 500 );
	}

	$redirect = parisan_core_calendar_admin_url( array( 'view' => 'day', 'date' => isset( $_POST['return_date'] ) ? sanitize_text_field( wp_unslash( $_POST['return_date'] ) ) : '', 'exception_deleted' => 1 ) );
	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_post_parisan_delete_schedule_exception', 'parisan_core_handle_schedule_exception_delete' );

/**
 * Save a salon holiday, specialist closure, special day, or custom-hours override.
 *
 * @return void
 */
function parisan_core_handle_date_override_save() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'اجازه انجام این عملیات را ندارید.', 'parisan-core' ), 403 );
	}
	check_admin_referer( 'parisan_save_date_override', 'parisan_override_nonce' );

	$scope = isset( $_POST['override_scope'] ) && is_string( $_POST['override_scope'] ) ? sanitize_key( wp_unslash( $_POST['override_scope'] ) ) : 'salon';
	$staff_id = 'staff' === $scope && isset( $_POST['override_staff_id'] ) && is_scalar( $_POST['override_staff_id'] ) ? absint( wp_unslash( $_POST['override_staff_id'] ) ) : 0;
	if ( $staff_id && ( 'parisan_staff' !== get_post_type( $staff_id ) || ! current_user_can( 'edit_post', $staff_id ) ) ) {
		wp_die( esc_html__( 'متخصص انتخاب‌شده معتبر نیست یا دسترسی ویرایش آن را ندارید.', 'parisan-core' ), 403 );
	}
	$date = parisan_core_calendar_parse_admin_date( isset( $_POST['override_date_jalali'] ) ? wp_unslash( $_POST['override_date_jalali'] ) : '' );
	if ( false === $date ) {
		wp_die( esc_html__( 'تاریخ شمسی برنامهٔ ویژه معتبر نیست. نمونه درست: ۱۴۰۵/۰۸/۲۲', 'parisan-core' ), 400 );
	}
	$state = isset( $_POST['override_state'] ) && is_string( $_POST['override_state'] ) ? sanitize_key( wp_unslash( $_POST['override_state'] ) ) : 'open';
	$closed = 'closed' === $state;
	$input = array(
		'staff_id'      => $staff_id,
		'override_date' => $date,
		'override_type' => $closed ? ( $staff_id ? 'closure' : 'holiday' ) : 'special_day',
		'is_closed'     => $closed,
		'start'         => isset( $_POST['override_start'] ) ? sanitize_text_field( wp_unslash( $_POST['override_start'] ) ) : '',
		'end'           => isset( $_POST['override_end'] ) ? sanitize_text_field( wp_unslash( $_POST['override_end'] ) ) : '',
		'break_start'   => isset( $_POST['override_break_start'] ) ? sanitize_text_field( wp_unslash( $_POST['override_break_start'] ) ) : '',
		'break_end'     => isset( $_POST['override_break_end'] ) ? sanitize_text_field( wp_unslash( $_POST['override_break_end'] ) ) : '',
		'note'          => isset( $_POST['override_note'] ) ? sanitize_text_field( wp_unslash( $_POST['override_note'] ) ) : '',
	);
	$result = parisan_core_save_date_override( $input );
	if ( is_wp_error( $result ) ) {
		$status = 'parisan_calendar_lock_unavailable' === $result->get_error_code() ? 409 : 400;
		wp_die( esc_html( $result->get_error_message() ), $status );
	}
	$redirect = parisan_core_calendar_admin_url(
		array(
			'view' => 'day',
			'date' => $date,
			'override_saved' => 1,
			'override_bookings' => (int) $result['bookings'],
		)
	);
	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_post_parisan_save_date_override', 'parisan_core_handle_date_override_save' );

/**
 * Delete a date override.
 *
 * @return void
 */
function parisan_core_handle_date_override_delete() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'اجازه انجام این عملیات را ندارید.', 'parisan-core' ), 403 );
	}
	$override_id = isset( $_POST['override_id'] ) && is_scalar( $_POST['override_id'] ) ? absint( wp_unslash( $_POST['override_id'] ) ) : 0;
	if ( ! $override_id ) {
		wp_die( esc_html__( 'برنامهٔ ویژه معتبر نیست.', 'parisan-core' ), 400 );
	}
	check_admin_referer( 'parisan_delete_date_override_' . $override_id, 'parisan_override_nonce' );

	global $wpdb;
	$overrides_table = $wpdb->prefix . 'parisan_schedule_date_overrides';
	$stored_staff_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT staff_id FROM {$overrides_table} WHERE id = %d LIMIT 1", $override_id ) );
	if ( $stored_staff_id && ( 'parisan_staff' !== get_post_type( $stored_staff_id ) || ! current_user_can( 'edit_post', $stored_staff_id ) ) ) {
		wp_die( esc_html__( 'دسترسی حذف این برنامهٔ متخصص را ندارید.', 'parisan-core' ), 403 );
	}
	$result = parisan_core_delete_date_override( $override_id );
	if ( is_wp_error( $result ) ) {
		wp_die( esc_html( $result->get_error_message() ), 409 );
	}
	$redirect = parisan_core_calendar_admin_url(
		array(
			'view' => 'day',
			'date' => isset( $_POST['return_date'] ) ? sanitize_text_field( wp_unslash( $_POST['return_date'] ) ) : '',
			'override_deleted' => 1,
		)
	);
	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_post_parisan_delete_date_override', 'parisan_core_handle_date_override_delete' );

/**
 * Register the Persian calendar page below Specialists.
 *
 * @return void
 */
function parisan_core_register_schedule_exception_page() {
	add_submenu_page(
		'edit.php?post_type=parisan_staff',
		__( 'تقویم و تعطیلات', 'parisan-core' ),
		__( 'تقویم و تعطیلات', 'parisan-core' ),
		'edit_others_posts',
		'parisan-schedule-exceptions',
		'parisan_core_render_schedule_exception_page'
	);
}
add_action( 'admin_menu', 'parisan_core_register_schedule_exception_page' );

/**
 * Render the complete calendar screen.
 *
 * @return void
 */
function parisan_core_render_schedule_exception_page() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'اجازه مشاهده این صفحه را ندارید.', 'parisan-core' ), 403 );
	}

	$staff = get_posts(
		array(
			'post_type' => 'parisan_staff',
			'post_status' => 'publish',
			'posts_per_page' => 200,
			'orderby' => 'title',
			'order' => 'ASC',
		)
	);
	$staff = array_values(
		array_filter(
			is_array( $staff ) ? $staff : array(),
			static function ( $person ) {
				return current_user_can( 'edit_post', $person->ID );
			}
		)
	);
	$staff_ids = array_map( 'absint', wp_list_pluck( $staff, 'ID' ) );
	$weekly_map = parisan_core_calendar_weekly_map( $staff );

	$view = isset( $_GET['view'] ) && is_string( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'month';
	$view = in_array( $view, array( 'month', 'week', 'day' ), true ) ? $view : 'month';
	$requested_date = isset( $_GET['date'] ) && is_string( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : '';
	$anchor = parisan_core_is_valid_local_date( $requested_date ) ? $requested_date : current_datetime()->format( 'Y-m-d' );
	$staff_filter = isset( $_GET['staff_filter'] ) ? absint( wp_unslash( $_GET['staff_filter'] ) ) : 0;
	$staff_filter = in_array( $staff_filter, $staff_ids, true ) ? $staff_filter : 0;
	$visible_staff = 0 === $staff_filter ? $staff : array_values( array_filter( $staff, static function ( $person ) use ( $staff_filter ) { return (int) $person->ID === $staff_filter; } ) );
	$visible_staff_ids = array_map( 'absint', wp_list_pluck( $visible_staff, 'ID' ) );
	$anchor_parts = parisan_core_gregorian_string_to_jalali( $anchor );
	$anchor_dt = new DateTimeImmutable( $anchor . ' 12:00:00', wp_timezone() );

	// Resolve an explicit Jalali month/year jump before navigation links are
	// rendered so previous/next actions stay anchored to the visible month.
	$jump_month = isset( $_GET['jmonth'] ) ? absint( wp_unslash( $_GET['jmonth'] ) ) : 0;
	$jump_year = isset( $_GET['jyear'] ) ? absint( wp_unslash( $_GET['jyear'] ) ) : 0;
	if ( $jump_month >= 1 && $jump_month <= 12 && $jump_year >= 1200 && $jump_year <= 1600 ) {
		$jumped = parisan_core_jalali_to_gregorian_string( $jump_year, $jump_month, 1 );
		if ( false !== $jumped ) {
			$anchor = $jumped;
			$anchor_parts = parisan_core_gregorian_string_to_jalali( $anchor );
			$anchor_dt = new DateTimeImmutable( $anchor . ' 12:00:00', wp_timezone() );
		}
	}
	?>
	<div class="wrap parisan-calendar-admin" dir="rtl">
		<h1><?php esc_html_e( 'تقویم کاری و تعطیلات', 'parisan-core' ); ?></h1>
		<p class="description"><?php esc_html_e( 'برنامه هفتگی هر متخصص پایهٔ تکرارشونده است. از این تقویم برای تعطیلی سالن، مرخصی، روز کاری ویژه و ساعت‌های متفاوت یک تاریخ مشخص استفاده کنید. همهٔ زمان‌ها بر اساس منطقه زمانی وردپرس محاسبه می‌شوند و تاریخ‌ها شمسی نمایش داده می‌شوند.', 'parisan-core' ); ?></p>
		<p class="parisan-calendar-timezone"><?php echo esc_html( sprintf( __( 'منطقه زمانی سالن: %s', 'parisan-core' ), wp_timezone()->getName() ) ); ?></p>

		<?php if ( isset( $_GET['override_saved'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'برنامهٔ ویژه ذخیره شد و از این پس در محاسبه زمان‌های آزاد اعمال می‌شود.', 'parisan-core' ); ?></p></div>
			<?php $override_bookings = isset( $_GET['override_bookings'] ) ? absint( wp_unslash( $_GET['override_bookings'] ) ) : 0; ?>
			<?php if ( $override_bookings > 0 ) : ?>
				<div class="notice notice-warning"><p><?php echo esc_html( sprintf( __( 'در این تاریخ %d رزرو در انتظار یا تأییدشده وجود دارد. رزروهای قبلی حذف نشده‌اند؛ پیش از نهایی‌کردن برنامه آن‌ها را بررسی کنید.', 'parisan-core' ), $override_bookings ) ); ?></p></div>
			<?php endif; ?>
		<?php elseif ( isset( $_GET['exception_saved'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'استثنای تقویم ذخیره شد.', 'parisan-core' ); ?></p></div>
			<?php $conflict_count = isset( $_GET['exception_conflicts'] ) ? absint( wp_unslash( $_GET['exception_conflicts'] ) ) : 0; ?>
			<?php if ( $conflict_count > 0 ) : ?>
				<div class="notice notice-warning"><p><?php echo esc_html( sprintf( __( 'توجه: این بازه با %d رزرو در انتظار یا تأییدشده تداخل دارد. رزروهای قبلی تغییر نکرده‌اند.', 'parisan-core' ), $conflict_count ) ); ?></p></div>
			<?php endif; ?>
		<?php elseif ( isset( $_GET['exception_deleted'] ) || isset( $_GET['override_deleted'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'برنامهٔ تقویم حذف شد.', 'parisan-core' ); ?></p></div>
		<?php endif; ?>

		<nav class="parisan-calendar-toolbar" aria-label="<?php esc_attr_e( 'ابزارهای تقویم', 'parisan-core' ); ?>">
			<div class="parisan-calendar-view-tabs" role="group" aria-label="<?php esc_attr_e( 'نمای تقویم', 'parisan-core' ); ?>">
				<?php foreach ( array( 'month' => __( 'ماه', 'parisan-core' ), 'week' => __( 'هفته', 'parisan-core' ), 'day' => __( 'روز', 'parisan-core' ) ) as $key => $label ) : ?>
					<a class="button <?php echo $view === $key ? 'button-primary' : 'button-secondary'; ?>" href="<?php echo esc_url( parisan_core_calendar_admin_url( array( 'view' => $key, 'date' => $anchor, 'staff_filter' => $staff_filter ) ) ); ?>" <?php echo $view === $key ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</div>
			<div class="parisan-calendar-period-nav">
				<?php
				if ( 'month' === $view ) {
					$previous_parts = parisan_core_jalali_add_months( $anchor_parts['year'], $anchor_parts['month'], -1 );
					$next_parts = parisan_core_jalali_add_months( $anchor_parts['year'], $anchor_parts['month'], 1 );
					$previous_date = false === $previous_parts ? $anchor : parisan_core_jalali_to_gregorian_string( $previous_parts[0], $previous_parts[1], 1 );
					$next_date = false === $next_parts ? $anchor : parisan_core_jalali_to_gregorian_string( $next_parts[0], $next_parts[1], 1 );
				} elseif ( 'week' === $view ) {
					$previous_date = $anchor_dt->modify( '-7 days' )->format( 'Y-m-d' );
					$next_date = $anchor_dt->modify( '+7 days' )->format( 'Y-m-d' );
				} else {
					$previous_date = $anchor_dt->modify( '-1 day' )->format( 'Y-m-d' );
					$next_date = $anchor_dt->modify( '+1 day' )->format( 'Y-m-d' );
				}
				?>
				<a class="button" href="<?php echo esc_url( parisan_core_calendar_admin_url( array( 'view' => $view, 'date' => $previous_date, 'staff_filter' => $staff_filter ) ) ); ?>" aria-label="<?php esc_attr_e( 'بازه قبلی', 'parisan-core' ); ?>">‹ <?php esc_html_e( 'قبلی', 'parisan-core' ); ?></a>
				<a class="button" href="<?php echo esc_url( parisan_core_calendar_admin_url( array( 'view' => $view, 'date' => current_datetime()->format( 'Y-m-d' ), 'staff_filter' => $staff_filter ) ) ); ?>"><?php esc_html_e( 'امروز', 'parisan-core' ); ?></a>
				<a class="button" href="<?php echo esc_url( parisan_core_calendar_admin_url( array( 'view' => $view, 'date' => $next_date, 'staff_filter' => $staff_filter ) ) ); ?>" aria-label="<?php esc_attr_e( 'بازه بعدی', 'parisan-core' ); ?>"><?php esc_html_e( 'بعدی', 'parisan-core' ); ?> ›</a>
			</div>
			<form class="parisan-calendar-jump" method="get">
				<input type="hidden" name="post_type" value="parisan_staff">
				<input type="hidden" name="page" value="parisan-schedule-exceptions">
				<input type="hidden" name="view" value="<?php echo esc_attr( $view ); ?>">
				<input type="hidden" name="staff_filter" value="<?php echo esc_attr( $staff_filter ); ?>">
				<label for="parisan-calendar-month"><?php esc_html_e( 'ماه شمسی', 'parisan-core' ); ?></label>
				<select id="parisan-calendar-month" name="jmonth">
					<?php foreach ( parisan_core_jalali_month_names() as $number => $name ) : ?>
						<option value="<?php echo esc_attr( $number ); ?>" <?php selected( $anchor_parts['month'], $number ); ?>><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
				<label for="parisan-calendar-year"><?php esc_html_e( 'سال', 'parisan-core' ); ?></label>
				<input id="parisan-calendar-year" type="number" name="jyear" min="1200" max="1600" step="1" value="<?php echo esc_attr( parisan_core_to_latin_digits( $anchor_parts['year'] ) ); ?>">
				<button class="button" type="submit"><?php esc_html_e( 'برو', 'parisan-core' ); ?></button>
			</form>
			<form class="parisan-calendar-filter" method="get">
				<input type="hidden" name="post_type" value="parisan_staff">
				<input type="hidden" name="page" value="parisan-schedule-exceptions">
				<input type="hidden" name="view" value="<?php echo esc_attr( $view ); ?>">
				<input type="hidden" name="date" value="<?php echo esc_attr( $anchor ); ?>">
				<label for="parisan-calendar-staff-filter"><?php esc_html_e( 'نمایش متخصص', 'parisan-core' ); ?></label>
				<select id="parisan-calendar-staff-filter" name="staff_filter">
					<option value="0"><?php esc_html_e( 'همهٔ متخصصان', 'parisan-core' ); ?></option>
					<?php foreach ( $staff as $person ) : ?>
						<option value="<?php echo esc_attr( $person->ID ); ?>" <?php selected( $staff_filter, $person->ID ); ?>><?php echo esc_html( get_the_title( $person ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<button class="button" type="submit"><?php esc_html_e( 'اعمال', 'parisan-core' ); ?></button>
			</form>
		</nav>

		<?php if ( 'month' === $view ) : ?>
			<?php
			$grid = parisan_core_jalali_month_grid( $anchor_parts['year'], $anchor_parts['month'] );
			$grid_start = $grid ? $grid[0]['gregorian'] : $anchor;
			$grid_end = $grid ? $grid[41]['gregorian'] : $anchor;
			$activity = parisan_core_calendar_activity_buckets( $grid_start, $grid_end, $visible_staff_ids );
			$overrides = parisan_core_get_date_overrides_for_range( $grid_start, $grid_end, $visible_staff_ids );
			?>
			<section class="parisan-calendar-panel" aria-labelledby="parisan-month-heading">
				<div class="parisan-calendar-panel-heading">
					<h2 id="parisan-month-heading"><?php echo esc_html( parisan_core_jalali_month_name( $anchor_parts['month'] ) . ' ' . parisan_core_to_persian_digits( $anchor_parts['year'] ) ); ?></h2>
					<p><?php echo esc_html( sprintf( __( 'از %s تا %s', 'parisan-core' ), parisan_core_format_jalali_date( $grid_start ), parisan_core_format_jalali_date( $grid_end ) ) ); ?></p>
				</div>
				<div class="parisan-calendar-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'جدول ماه شمسی', 'parisan-core' ); ?>">
					<table class="parisan-calendar-table parisan-calendar-month" data-parisan-calendar-grid>
						<caption class="screen-reader-text"><?php echo esc_html( sprintf( __( 'تقویم %s %s', 'parisan-core' ), parisan_core_jalali_month_name( $anchor_parts['month'] ), $anchor_parts['year'] ) ); ?></caption>
						<thead><tr>
							<?php foreach ( parisan_core_jalali_weekday_names() as $weekday_name ) : ?>
								<th scope="col"><?php echo esc_html( $weekday_name ); ?></th>
							<?php endforeach; ?>
						</tr></thead>
						<tbody>
							<?php foreach ( array_chunk( $grid, 7 ) as $week ) : ?>
								<tr>
									<?php foreach ( $week as $cell ) : ?>
										<?php
										$state = parisan_core_calendar_day_state( $cell['gregorian'], $visible_staff, $weekly_map, $overrides );
										$booking_count = isset( $activity['bookings'][ $cell['gregorian'] ] ) ? (int) $activity['bookings'][ $cell['gregorian'] ] : 0;
										$exception_count = isset( $activity['exceptions'][ $cell['gregorian'] ] ) ? (int) $activity['exceptions'][ $cell['gregorian'] ] : 0;
										$is_today = $cell['gregorian'] === current_datetime()->format( 'Y-m-d' );
										$classes = array( 'parisan-calendar-day', 'is-' . $state['key'] );
										if ( ! $cell['in_month'] ) {
											$classes[] = 'is-outside';
										}
										if ( $is_today ) {
											$classes[] = 'is-today';
										}
										if ( $exception_count ) {
											$classes[] = 'has-exception';
										}
										if ( $booking_count ) {
											$classes[] = 'has-bookings';
										}
										$day_label = parisan_core_format_jalali_date( $cell['gregorian'] );
										$aria_label = $day_label . '، ' . $state['label'];
										if ( $booking_count ) {
											$aria_label .= '، ' . sprintf( _n( '%d رزرو', '%d رزرو', $booking_count, 'parisan-core' ), $booking_count );
										}
										if ( $exception_count ) {
											$aria_label .= '، ' . sprintf( _n( '%d استثنا', '%d استثنا', $exception_count, 'parisan-core' ), $exception_count );
										}
										?>
										<td class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-date="<?php echo esc_attr( $cell['gregorian'] ); ?>">
											<a href="<?php echo esc_url( parisan_core_calendar_admin_url( array( 'view' => 'day', 'date' => $cell['gregorian'], 'staff_filter' => $staff_filter ) ) ); ?>" data-calendar-day aria-label="<?php echo esc_attr( $aria_label ); ?>" <?php echo $is_today ? 'aria-current="date"' : ''; ?>>
												<span class="parisan-calendar-day-number"><?php echo esc_html( parisan_core_to_persian_digits( $cell['day'] ) ); ?></span>
												<span class="parisan-calendar-day-gregorian"><?php echo esc_html( parisan_core_to_persian_digits( $cell['gregorian'] ) ); ?></span>
												<span class="parisan-calendar-day-state"><?php echo esc_html( $state['label'] ); ?></span>
												<span class="parisan-calendar-day-meta">
													<?php if ( $booking_count ) : ?><span class="parisan-calendar-badge is-booking"><?php echo esc_html( sprintf( __( '%d رزرو', 'parisan-core' ), parisan_core_to_persian_digits( $booking_count ) ) ); ?></span><?php endif; ?>
													<?php if ( $exception_count ) : ?><span class="parisan-calendar-badge is-exception"><?php echo esc_html( sprintf( __( '%d استثنا', 'parisan-core' ), parisan_core_to_persian_digits( $exception_count ) ) ); ?></span><?php endif; ?>
												</span>
											</a>
										</td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</section>
		<?php elseif ( 'week' === $view ) : ?>
			<?php
			$week_start_dt = $anchor_dt->modify( '-' . parisan_core_jalali_weekday_index_from_php( (int) $anchor_dt->format( 'w' ) ) . ' days' );
			$week_days = array();
			for ( $index = 0; $index < 7; $index++ ) {
				$week_days[] = $week_start_dt->modify( '+' . $index . ' days' )->format( 'Y-m-d' );
			}
			$activity = parisan_core_calendar_activity_buckets( $week_days[0], $week_days[6], $visible_staff_ids );
			$overrides = parisan_core_get_date_overrides_for_range( $week_days[0], $week_days[6], $visible_staff_ids );
			?>
			<section class="parisan-calendar-panel" aria-labelledby="parisan-week-heading">
				<div class="parisan-calendar-panel-heading">
					<h2 id="parisan-week-heading"><?php esc_html_e( 'نمای هفته', 'parisan-core' ); ?></h2>
					<p><?php echo esc_html( sprintf( __( '%s تا %s', 'parisan-core' ), parisan_core_format_jalali_date( $week_days[0], array( 'with_weekday' => true ) ), parisan_core_format_jalali_date( $week_days[6], array( 'with_weekday' => true ) ) ) ); ?></p>
				</div>
				<div class="parisan-calendar-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'جدول هفته شمسی', 'parisan-core' ); ?>">
					<table class="parisan-calendar-table parisan-calendar-week">
						<thead><tr>
							<th scope="col"><?php esc_html_e( 'متخصص', 'parisan-core' ); ?></th>
							<?php foreach ( $week_days as $day ) : ?>
								<?php $state = parisan_core_calendar_day_state( $day, $visible_staff, $weekly_map, $overrides ); ?>
								<th scope="col" class="is-<?php echo esc_attr( $state['key'] ); ?>">
									<a href="<?php echo esc_url( parisan_core_calendar_admin_url( array( 'view' => 'day', 'date' => $day, 'staff_filter' => $staff_filter ) ) ); ?>"><?php echo esc_html( parisan_core_format_jalali_date( $day, array( 'with_weekday' => true ) ) ); ?></a>
									<span class="parisan-calendar-header-state"><?php echo esc_html( $state['label'] ); ?></span>
								</th>
							<?php endforeach; ?>
						</tr></thead>
						<tbody>
							<?php if ( ! $visible_staff ) : ?>
								<tr><td colspan="8"><?php esc_html_e( 'متخصص منتشرشده و قابل ویرایشی وجود ندارد.', 'parisan-core' ); ?></td></tr>
							<?php endif; ?>
							<?php foreach ( $visible_staff as $person ) : ?>
								<?php $staff_id = (int) $person->ID; ?>
								<tr>
									<th scope="row"><?php echo esc_html( get_the_title( $person ) ); ?></th>
									<?php foreach ( $week_days as $day ) : ?>
										<?php
										$staff_override = isset( $overrides[ $staff_id . ':' . $day ] ) ? $overrides[ $staff_id . ':' . $day ] : null;
										$salon_override = isset( $overrides[ '0:' . $day ] ) ? $overrides[ '0:' . $day ] : null;
										$resolved = parisan_core_resolve_effective_day_schedule( $day, isset( $weekly_map[ $staff_id ] ) ? $weekly_map[ $staff_id ] : array(), $staff_override, $salon_override );
										$booking_count = isset( $activity['booking_staff'][ $day ][ $staff_id ] ) ? (int) $activity['booking_staff'][ $day ][ $staff_id ] : 0;
										$exception_count = isset( $activity['exception_staff'][ $day ][ $staff_id ] ) ? (int) $activity['exception_staff'][ $day ][ $staff_id ] : 0;
										?>
										<td class="<?php echo esc_attr( empty( $resolved['off'] ) ? 'is-open' : 'is-closed' ); ?>">
											<span class="parisan-calendar-hours"><?php echo esc_html( parisan_core_calendar_hours_label( $resolved ) ); ?></span>
											<span class="parisan-calendar-source"><?php echo esc_html( parisan_core_calendar_source_label( isset( $resolved['source'] ) ? $resolved['source'] : '' ) ); ?></span>
											<?php if ( $booking_count ) : ?><span class="parisan-calendar-badge is-booking"><?php echo esc_html( sprintf( __( '%d رزرو', 'parisan-core' ), parisan_core_to_persian_digits( $booking_count ) ) ); ?></span><?php endif; ?>
											<?php if ( $exception_count ) : ?><span class="parisan-calendar-badge is-exception"><?php echo esc_html( sprintf( __( '%d استثنا', 'parisan-core' ), parisan_core_to_persian_digits( $exception_count ) ) ); ?></span><?php endif; ?>
										</td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</section>
		<?php else : ?>
			<?php
			$day_activity = parisan_core_calendar_activity_buckets( $anchor, $anchor, $visible_staff_ids );
			$day_overrides = parisan_core_get_date_overrides_for_range( $anchor, $anchor, $visible_staff_ids );
			$day_state = parisan_core_calendar_day_state( $anchor, $visible_staff, $weekly_map, $day_overrides );
			?>
			<section class="parisan-calendar-panel" aria-labelledby="parisan-day-heading">
				<div class="parisan-calendar-panel-heading">
					<h2 id="parisan-day-heading"><?php echo esc_html( parisan_core_format_jalali_date( $anchor, array( 'with_weekday' => true ) ) ); ?></h2>
					<p><?php echo esc_html( sprintf( __( 'تاریخ میلادی: %s — وضعیت: %s', 'parisan-core' ), parisan_core_to_persian_digits( $anchor ), $day_state['label'] ) ); ?></p>
				</div>
				<div class="parisan-calendar-day-actions">
					<a class="button" href="<?php echo esc_url( parisan_core_calendar_admin_url( array( 'view' => 'day', 'date' => $anchor_dt->modify( '-1 day' )->format( 'Y-m-d' ), 'staff_filter' => $staff_filter ) ) ); ?>">‹ <?php esc_html_e( 'روز قبل', 'parisan-core' ); ?></a>
					<a class="button" href="<?php echo esc_url( parisan_core_calendar_admin_url( array( 'view' => 'day', 'date' => $anchor_dt->modify( '+1 day' )->format( 'Y-m-d' ), 'staff_filter' => $staff_filter ) ) ); ?>"><?php esc_html_e( 'روز بعد', 'parisan-core' ); ?> ›</a>
				</div>
				<div class="parisan-calendar-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'جزئیات روز', 'parisan-core' ); ?>">
					<table class="parisan-calendar-table parisan-calendar-day-detail">
						<thead><tr><th scope="col"><?php esc_html_e( 'متخصص', 'parisan-core' ); ?></th><th scope="col"><?php esc_html_e( 'برنامه مؤثر', 'parisan-core' ); ?></th><th scope="col"><?php esc_html_e( 'منبع', 'parisan-core' ); ?></th><th scope="col"><?php esc_html_e( 'رزروها', 'parisan-core' ); ?></th><th scope="col"><?php esc_html_e( 'استثناها', 'parisan-core' ); ?></th></tr></thead>
						<tbody>
							<?php if ( ! $visible_staff ) : ?>
								<tr><td colspan="5"><?php esc_html_e( 'متخصص منتشرشده و قابل ویرایشی وجود ندارد.', 'parisan-core' ); ?></td></tr>
							<?php endif; ?>
							<?php foreach ( $visible_staff as $person ) : ?>
								<?php
								$staff_id = (int) $person->ID;
								$resolved = parisan_core_resolve_effective_day_schedule( $anchor, isset( $weekly_map[ $staff_id ] ) ? $weekly_map[ $staff_id ] : array(), isset( $day_overrides[ $staff_id . ':' . $anchor ] ) ? $day_overrides[ $staff_id . ':' . $anchor ] : null, isset( $day_overrides[ '0:' . $anchor ] ) ? $day_overrides[ '0:' . $anchor ] : null );
								$booking_count = isset( $day_activity['booking_staff'][ $anchor ][ $staff_id ] ) ? (int) $day_activity['booking_staff'][ $anchor ][ $staff_id ] : 0;
								$exception_count = isset( $day_activity['exception_staff'][ $anchor ][ $staff_id ] ) ? (int) $day_activity['exception_staff'][ $anchor ][ $staff_id ] : 0;
								?>
								<tr>
									<th scope="row"><?php echo esc_html( get_the_title( $person ) ); ?></th>
									<td><?php echo esc_html( parisan_core_calendar_hours_label( $resolved ) ); ?></td>
									<td><?php echo esc_html( parisan_core_calendar_source_label( isset( $resolved['source'] ) ? $resolved['source'] : '' ) ); ?></td>
									<td><?php echo esc_html( parisan_core_to_persian_digits( $booking_count ) ); ?></td>
									<td><?php echo esc_html( parisan_core_to_persian_digits( $exception_count ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php if ( $day_overrides ) : ?>
					<h3><?php esc_html_e( 'برنامه‌های ویژه این تاریخ', 'parisan-core' ); ?></h3>
					<table class="widefat striped parisan-calendar-overrides-table"><thead><tr><th><?php esc_html_e( 'دامنه', 'parisan-core' ); ?></th><th><?php esc_html_e( 'وضعیت', 'parisan-core' ); ?></th><th><?php esc_html_e( 'ساعت‌ها', 'parisan-core' ); ?></th><th><?php esc_html_e( 'توضیح', 'parisan-core' ); ?></th><th><?php esc_html_e( 'عملیات', 'parisan-core' ); ?></th></tr></thead><tbody>
						<?php foreach ( $day_overrides as $override ) : ?>
							<?php if ( $override['staff_id'] && ! current_user_can( 'edit_post', $override['staff_id'] ) ) { continue; } ?>
							<tr>
								<td><?php echo esc_html( $override['staff_id'] ? get_the_title( $override['staff_id'] ) : __( 'کل سالن', 'parisan-core' ) ); ?></td>
								<td><?php echo esc_html( ! empty( $override['is_closed'] ) ? __( 'تعطیل', 'parisan-core' ) : __( 'باز', 'parisan-core' ) ); ?></td>
								<td><?php echo esc_html( parisan_core_calendar_hours_label( $override ) ); ?></td>
								<td><?php echo esc_html( $override['note'] ); ?></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="parisan_delete_date_override">
										<input type="hidden" name="override_id" value="<?php echo esc_attr( $override['id'] ); ?>">
										<input type="hidden" name="override_staff_id" value="<?php echo esc_attr( $override['staff_id'] ); ?>">
										<input type="hidden" name="return_date" value="<?php echo esc_attr( $anchor ); ?>">
										<?php wp_nonce_field( 'parisan_delete_date_override_' . $override['id'], 'parisan_override_nonce' ); ?>
										<?php submit_button( __( 'حذف', 'parisan-core' ), 'delete small', 'submit', false, array( 'onclick' => "return confirm('" . esc_js( __( 'از حذف این برنامهٔ ویژه مطمئن هستید؟', 'parisan-core' ) ) . "');" ) ); ?>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody></table>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<div class="parisan-calendar-legend" aria-label="<?php esc_attr_e( 'راهنمای وضعیت‌ها', 'parisan-core' ); ?>">
			<span class="is-open"><?php esc_html_e( 'باز', 'parisan-core' ); ?></span>
			<span class="is-closed"><?php esc_html_e( 'تعطیل هفتگی', 'parisan-core' ); ?></span>
			<span class="is-holiday"><?php esc_html_e( 'تعطیلی سالن', 'parisan-core' ); ?></span>
			<span class="is-special"><?php esc_html_e( 'روز کاری ویژه', 'parisan-core' ); ?></span>
			<span class="is-exception"><?php esc_html_e( 'استثنای جزئی', 'parisan-core' ); ?></span>
			<span class="is-booking"><?php esc_html_e( 'رزرو موجود', 'parisan-core' ); ?></span>
		</div>

		<div class="parisan-calendar-forms">
			<section class="parisan-calendar-form-card" aria-labelledby="parisan-override-form-title">
				<h2 id="parisan-override-form-title"><?php esc_html_e( 'ثبت تعطیلی، روز کاری ویژه یا ساعت متفاوت', 'parisan-core' ); ?></h2>
				<p><?php esc_html_e( 'تعطیلی سالن برای همهٔ متخصصان اعمال می‌شود. برای مرخصی یک متخصص، دامنه را روی او بگذارید. برنامهٔ ویژه، قاعده هفتگی همان تاریخ را جایگزین می‌کند.', 'parisan-core' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-parisan-override-form>
					<input type="hidden" name="action" value="parisan_save_date_override">
					<?php wp_nonce_field( 'parisan_save_date_override', 'parisan_override_nonce' ); ?>
					<div class="parisan-calendar-form-grid">
						<p><label for="parisan-override-scope"><?php esc_html_e( 'دامنه', 'parisan-core' ); ?></label>
							<select id="parisan-override-scope" name="override_scope">
								<option value="salon"><?php esc_html_e( 'کل سالن', 'parisan-core' ); ?></option>
								<option value="staff"><?php esc_html_e( 'یک متخصص', 'parisan-core' ); ?></option>
							</select>
						</p>
						<p><label for="parisan-override-staff"><?php esc_html_e( 'متخصص', 'parisan-core' ); ?></label>
							<select id="parisan-override-staff" name="override_staff_id" disabled>
								<?php foreach ( $staff as $person ) : ?>
									<option value="<?php echo esc_attr( $person->ID ); ?>" <?php selected( $staff_filter, $person->ID ); ?>><?php echo esc_html( get_the_title( $person ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
						<p><label for="parisan-override-date"><?php esc_html_e( 'تاریخ شمسی', 'parisan-core' ); ?></label>
							<input id="parisan-override-date" type="text" name="override_date_jalali" inputmode="numeric" placeholder="۱۴۰۵/۰۸/۲۲" value="<?php echo esc_attr( parisan_core_to_persian_digits( sprintf( '%04d/%02d/%02d', $anchor_parts['year'], $anchor_parts['month'], $anchor_parts['day'] ) ) ); ?>" required>
							<span class="description"><?php esc_html_e( 'نمونه: ۱۴۰۵/۰۸/۲۲', 'parisan-core' ); ?></span>
						</p>
						<p><label for="parisan-override-state"><?php esc_html_e( 'وضعیت روز', 'parisan-core' ); ?></label>
							<select id="parisan-override-state" name="override_state" data-parisan-override-state>
								<option value="open"><?php esc_html_e( 'باز با ساعت سفارشی', 'parisan-core' ); ?></option>
								<option value="closed"><?php esc_html_e( 'تعطیل', 'parisan-core' ); ?></option>
							</select>
						</p>
						<p data-parisan-time-field><label for="parisan-override-start"><?php esc_html_e( 'از ساعت', 'parisan-core' ); ?></label><input id="parisan-override-start" type="time" name="override_start" value="09:00"></p>
						<p data-parisan-time-field><label for="parisan-override-end"><?php esc_html_e( 'تا ساعت', 'parisan-core' ); ?></label><input id="parisan-override-end" type="time" name="override_end" value="18:00"></p>
						<p data-parisan-time-field><label for="parisan-override-break-start"><?php esc_html_e( 'شروع استراحت', 'parisan-core' ); ?></label><input id="parisan-override-break-start" type="time" name="override_break_start"></p>
						<p data-parisan-time-field><label for="parisan-override-break-end"><?php esc_html_e( 'پایان استراحت', 'parisan-core' ); ?></label><input id="parisan-override-break-end" type="time" name="override_break_end"></p>
						<p class="parisan-calendar-field-wide"><label for="parisan-override-note"><?php esc_html_e( 'توضیح', 'parisan-core' ); ?></label><input id="parisan-override-note" type="text" maxlength="255" name="override_note" placeholder="<?php esc_attr_e( 'مثلاً تعطیلی رسمی، مرخصی یا جلسه', 'parisan-core' ); ?>"></p>
					</div>
					<?php submit_button( __( 'ذخیره برنامهٔ ویژه', 'parisan-core' ) ); ?>
				</form>
			</section>

			<section class="parisan-calendar-form-card" aria-labelledby="parisan-exception-form-title">
				<h2 id="parisan-exception-form-title"><?php esc_html_e( 'مسدودکردن بخشی از ساعات یک متخصص', 'parisan-core' ); ?></h2>
				<p><?php esc_html_e( 'برای مرخصی چندساعته، جلسه یا غیبت بخشی از روز از این فرم استفاده کنید. این استثنا فقط همان بازه را از دسترس خارج می‌کند.', 'parisan-core' ); ?></p>
				<?php if ( $staff ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-parisan-exception-form>
						<input type="hidden" name="action" value="parisan_create_schedule_exception">
						<?php wp_nonce_field( 'parisan_create_schedule_exception', 'parisan_exception_nonce' ); ?>
						<div class="parisan-calendar-form-grid">
							<p><label for="parisan-exception-staff"><?php esc_html_e( 'متخصص', 'parisan-core' ); ?></label><select required id="parisan-exception-staff" name="staff_id"><?php foreach ( $staff as $person ) : ?><option value="<?php echo esc_attr( $person->ID ); ?>"><?php echo esc_html( get_the_title( $person ) ); ?></option><?php endforeach; ?></select></p>
							<p><label for="parisan-exception-date"><?php esc_html_e( 'تاریخ شمسی', 'parisan-core' ); ?></label><input required type="text" id="parisan-exception-date" name="exception_date" inputmode="numeric" placeholder="۱۴۰۵/۰۸/۲۲" value="<?php echo esc_attr( parisan_core_to_persian_digits( sprintf( '%04d/%02d/%02d', $anchor_parts['year'], $anchor_parts['month'], $anchor_parts['day'] ) ) ); ?>"></p>
							<p class="parisan-calendar-field-wide"><label><input type="checkbox" name="all_day" value="1" data-parisan-all-day> <?php esc_html_e( 'تمام روز تعطیل / مرخصی', 'parisan-core' ); ?></label></p>
							<p data-parisan-exception-time><label for="parisan-exception-start"><?php esc_html_e( 'از ساعت', 'parisan-core' ); ?></label><input type="time" id="parisan-exception-start" name="start_time" value="09:00"></p>
							<p data-parisan-exception-time><label for="parisan-exception-end"><?php esc_html_e( 'تا ساعت', 'parisan-core' ); ?></label><input type="time" id="parisan-exception-end" name="end_time" value="18:00"></p>
							<p class="parisan-calendar-field-wide"><label for="parisan-exception-note"><?php esc_html_e( 'توضیح', 'parisan-core' ); ?></label><input type="text" id="parisan-exception-note" maxlength="255" name="note" placeholder="<?php esc_attr_e( 'مثلاً مرخصی، تعطیلی رسمی یا جلسه', 'parisan-core' ); ?>"></p>
						</div>
						<?php submit_button( __( 'ثبت استثنا', 'parisan-core' ) ); ?>
					</form>
				<?php else : ?>
					<p><?php esc_html_e( 'متخصص منتشرشده و قابل ویرایشی وجود ندارد. ابتدا یک متخصص ثبت کنید.', 'parisan-core' ); ?></p>
				<?php endif; ?>
			</section>
		</div>
	</div>
	<?php
}
