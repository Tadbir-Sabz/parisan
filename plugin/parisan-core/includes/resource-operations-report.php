<?php
/**
 * Resource utilization and maintenance reporting.
 *
 * Reports reserved/completed service time separately from maintenance time;
 * no inferred availability percentage is shown because resource schedules
 * may be unrestricted or vary by weekday.
 *
 * @package ParisanCore
 */
defined( 'ABSPATH' ) || exit;

function parisan_core_resource_report_date_range() {
	$tz = wp_timezone();
	$today = new DateTimeImmutable( 'today', $tz );
	$from = isset( $_REQUEST['from'] ) && is_string( $_REQUEST['from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['from'] ) ) : $today->modify( '-29 days' )->format( 'Y-m-d' );
	$to = isset( $_REQUEST['to'] ) && is_string( $_REQUEST['to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['to'] ) ) : $today->format( 'Y-m-d' );
	$parse = static function ( $value ) use ( $tz ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $value ) ) { return false; }
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, $tz );
		$errors = DateTimeImmutable::getLastErrors();
		return false !== $date && ( ! is_array( $errors ) || ( 0 === $errors['warning_count'] && 0 === $errors['error_count'] ) ) && $date->format( 'Y-m-d' ) === $value ? $date : false;
	};
	$start = $parse( $from );
	$end = $parse( $to );
	if ( ! $start || ! $end || $end < $start || $start < $today->modify( '-730 days' ) || $end > $today->modify( '+365 days' ) ) {
		return new WP_Error( 'parisan_invalid_resource_report_range', __( 'بازه تاریخ گزارش منابع معتبر نیست.', 'parisan-core' ) );
	}
	$utc = new DateTimeZone( 'UTC' );
	return array( 'from' => $from, 'to' => $to, 'start_utc' => $start->setTimezone( $utc )->format( 'Y-m-d H:i:s' ), 'end_utc' => $end->modify( '+1 day' )->setTimezone( $utc )->format( 'Y-m-d H:i:s' ) );
}

function parisan_core_get_resource_report_rows( $range, $type_filter = '' ) {
	global $wpdb;
	$resources = get_posts( array( 'post_type' => 'parisan_resource', 'post_status' => 'publish', 'posts_per_page' => 300, 'orderby' => 'title', 'order' => 'ASC' ) );
	$rows = array();
	$bookings = $wpdb->prefix . 'parisan_bookings';
	$relations = $wpdb->prefix . 'parisan_booking_resources';
	$maintenance = $wpdb->prefix . 'parisan_resource_maintenance';
	foreach ( $resources as $resource ) {
		if ( ! current_user_can( 'edit_post', $resource->ID ) ) { continue; }
		$type = get_post_meta( $resource->ID, '_parisan_resource_type', true );
		$type = in_array( $type, array( 'room', 'bed', 'chair', 'device', 'other' ), true ) ? $type : 'other';
		if ( $type_filter && $type !== $type_filter ) { continue; }
		$resource_id = (int) $resource->ID;
		$booking_rows = $wpdb->get_row( $wpdb->prepare(
			"SELECT
			COUNT(DISTINCT CASE WHEN b.status IN ('pending','confirmed') THEN b.id END) AS reserved_count,
			COALESCE(SUM(CASE WHEN b.status IN ('pending','confirmed') THEN TIMESTAMPDIFF(MINUTE,GREATEST(b.starts_at_utc,%s),LEAST(b.ends_at_utc,%s)) ELSE 0 END),0) AS reserved_minutes,
			COUNT(DISTINCT CASE WHEN b.status='completed' THEN b.id END) AS completed_count,
			COALESCE(SUM(CASE WHEN b.status='completed' THEN TIMESTAMPDIFF(MINUTE,GREATEST(b.starts_at_utc,%s),LEAST(b.ends_at_utc,%s)) ELSE 0 END),0) AS completed_minutes
			FROM {$bookings} b
			WHERE (b.resource_id=%d OR EXISTS (SELECT 1 FROM {$relations} br WHERE br.booking_id=b.id AND br.resource_id=%d))
			AND b.starts_at_utc < %s AND b.ends_at_utc > %s",
			$range['start_utc'], $range['end_utc'], $range['start_utc'], $range['end_utc'], $resource_id, $range['start_utc'], $range['end_utc'], $resource_id, $range['end_utc'], $range['start_utc']
		), ARRAY_A );
		$maintenance_rows = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) AS maintenance_count, COALESCE(SUM(TIMESTAMPDIFF(MINUTE,GREATEST(starts_at_utc,%s),LEAST(ends_at_utc,%s))),0) AS maintenance_minutes
			FROM {$maintenance} WHERE resource_id=%d AND status='active' AND starts_at_utc < %s AND ends_at_utc > %s",
			$resource_id, $range['end_utc'], $range['start_utc']
		), ARRAY_A );
		$rows[] = array(
			'resource_id' => $resource_id,
			'name' => get_the_title( $resource_id ),
			'type' => $type,
			'reserved_count' => (int) $booking_rows['reserved_count'],
			'reserved_minutes' => (int) $booking_rows['reserved_minutes'],
			'completed_count' => (int) $booking_rows['completed_count'],
			'completed_minutes' => (int) $booking_rows['completed_minutes'],
			'maintenance_count' => (int) $maintenance_rows['maintenance_count'],
			'maintenance_minutes' => (int) $maintenance_rows['maintenance_minutes'],
		);
	}
	return $rows;
}

function parisan_core_handle_resource_report_csv() {
	if ( ! current_user_can( 'edit_others_posts' ) ) { wp_die( esc_html__( 'اجازه خروجی گزارش منابع را ندارید.', 'parisan-core' ), 403 ); }
	check_admin_referer( 'parisan_resource_report_csv', 'parisan_resource_report_nonce' );
	$range = parisan_core_resource_report_date_range();
	if ( is_wp_error( $range ) ) { wp_die( esc_html( $range->get_error_message() ), 400 ); }
	$type = isset( $_GET['resource_type'] ) && is_string( $_GET['resource_type'] ) ? sanitize_key( wp_unslash( $_GET['resource_type'] ) ) : '';
	if ( ! in_array( $type, array( '', 'room', 'bed', 'chair', 'device', 'other' ), true ) ) { $type = ''; }
	$rows = parisan_core_get_resource_report_rows( $range, $type );
	$filename = 'parisan-resource-report-' . $range['from'] . '-' . $range['to'] . '.csv';
	nocache_headers();
	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename=' . $filename );
	$out = fopen( 'php://output', 'w' );
	if ( false === $out ) { wp_die( esc_html__( 'ساخت خروجی CSV انجام نشد.', 'parisan-core' ), 500 ); }
	fwrite( $out, "\xEF\xBB\xBF" );
	fputcsv( $out, array( 'Resource', 'Type', 'Reserved appointments', 'Reserved minutes', 'Completed appointments', 'Completed minutes', 'Active maintenance windows', 'Maintenance minutes' ) );
	foreach ( $rows as $row ) {
		$name = (string) $row['name'];
		if ( preg_match( '/^[=+@\-\t\r]/', $name ) ) { $name = "'" . $name; }
		fputcsv( $out, array( $name, $row['type'], $row['reserved_count'], $row['reserved_minutes'], $row['completed_count'], $row['completed_minutes'], $row['maintenance_count'], $row['maintenance_minutes'] ) );
	}
	fclose( $out );
	exit;
}
add_action( 'admin_post_parisan_resource_report_csv', 'parisan_core_handle_resource_report_csv' );

function parisan_core_register_resource_report_page() {
	add_submenu_page( 'edit.php?post_type=parisan_staff', __( 'گزارش منابع سالن', 'parisan-core' ), __( 'گزارش منابع', 'parisan-core' ), 'edit_others_posts', 'parisan-resource-report', 'parisan_core_render_resource_report_page' );
}
add_action( 'admin_menu', 'parisan_core_register_resource_report_page' );

function parisan_core_render_resource_report_page() {
	if ( ! current_user_can( 'edit_others_posts' ) ) { wp_die( esc_html__( 'اجازه مشاهده گزارش منابع را ندارید.', 'parisan-core' ), 403 ); }
	$range = parisan_core_resource_report_date_range();
	echo '<div class="wrap"><h1>گزارش عملکرد منابع سالن</h1><p>زمان رزروهای در انتظار/تأییدشده، زمان خدمات تکمیل‌شده و توقف تعمیراتی جداگانه گزارش می‌شوند. این گزارش درصد بهره‌برداری یا ظرفیت آزاد را حدس نمی‌زند؛ زیرا ساعات کاری هر منبع می‌تواند متفاوت یا نامحدود باشد.</p>';
	if ( is_wp_error( $range ) ) { echo '<div class="notice notice-error"><p>' . esc_html( $range->get_error_message() ) . '</p></div></div>'; return; }
	$type = isset( $_GET['resource_type'] ) && is_string( $_GET['resource_type'] ) ? sanitize_key( wp_unslash( $_GET['resource_type'] ) ) : '';
	if ( ! in_array( $type, array( '', 'room', 'bed', 'chair', 'device', 'other' ), true ) ) { $type = ''; }
	echo '<form method="get"><input type="hidden" name="post_type" value="parisan_staff"><input type="hidden" name="page" value="parisan-resource-report"><label>از <input type="date" name="from" value="' . esc_attr( $range['from'] ) . '"></label> <label>تا <input type="date" name="to" value="' . esc_attr( $range['to'] ) . '"></label> <label>نوع منبع <select name="resource_type"><option value="">همه</option>';
	$types = array( 'room' => 'اتاق', 'bed' => 'تخت', 'chair' => 'صندلی', 'device' => 'دستگاه', 'other' => 'سایر' );
	foreach ( $types as $key => $label ) { echo '<option value="' . esc_attr( $key ) . '"' . selected( $type, $key, false ) . '>' . esc_html( $label ) . '</option>'; }
	echo '</select></label> <button class="button">اعمال</button></form>';
	$rows = parisan_core_get_resource_report_rows( $range, $type );
	echo '<form method="get" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="parisan_resource_report_csv"><input type="hidden" name="from" value="' . esc_attr( $range['from'] ) . '"><input type="hidden" name="to" value="' . esc_attr( $range['to'] ) . '"><input type="hidden" name="resource_type" value="' . esc_attr( $type ) . '">';
	wp_nonce_field( 'parisan_resource_report_csv', 'parisan_resource_report_nonce' );
	echo '<button class="button">دریافت CSV</button></form>';
	echo '<table class="widefat striped"><thead><tr><th>منبع</th><th>نوع</th><th>رزرو فعال (تعداد / دقیقه)</th><th>خدمت تکمیل‌شده (تعداد / دقیقه)</th><th>توقف فعال (تعداد / دقیقه)</th></tr></thead><tbody>';
	foreach ( $rows as $row ) {
		echo '<tr><td>' . esc_html( $row['name'] ) . '</td><td>' . esc_html( $types[ $row['type'] ] ) . '</td><td>' . esc_html( $row['reserved_count'] . ' / ' . number_format_i18n( $row['reserved_minutes'] ) ) . '</td><td>' . esc_html( $row['completed_count'] . ' / ' . number_format_i18n( $row['completed_minutes'] ) ) . '</td><td>' . esc_html( $row['maintenance_count'] . ' / ' . number_format_i18n( $row['maintenance_minutes'] ) ) . '</td></tr>';
	}
	if ( ! $rows ) { echo '<tr><td colspan="5">منبع قابل‌مشاهده‌ای در این محدوده وجود ندارد.</td></tr>'; }
	echo '</tbody></table><p>بازه گزارش بر اساس تاریخ محلی سالن انتخاب و به UTC تبدیل می‌شود. فقط منابعی نمایش داده می‌شوند که کاربر جاری اجازه ویرایش آن‌ها را دارد. زمان رزرو شامل مدت ثبت‌شده خدمت است و بافرها جداگانه محاسبه نشده‌اند.</p></div>';
}
