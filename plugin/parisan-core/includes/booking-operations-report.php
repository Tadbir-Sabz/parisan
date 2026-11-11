<?php
/**
 * Permission-scoped operational booking reports and CSV export.
 *
 * @package ParisanCore
 */
defined( 'ABSPATH' ) || exit;

function parisan_core_register_booking_report_page() {
	add_submenu_page( 'edit.php?post_type=parisan_staff', __( 'گزارش عملکرد نوبت‌ها', 'parisan-core' ), __( 'گزارش عملکرد', 'parisan-core' ), 'edit_others_posts', 'parisan-booking-report', 'parisan_core_render_booking_report_page' );
}
add_action( 'admin_menu', 'parisan_core_register_booking_report_page' );

/** Resolve a validated inclusive salon-local date range to UTC bounds. */
function parisan_core_booking_report_range( $from, $to ) {
	$timezone = wp_timezone();
	$start = DateTimeImmutable::createFromFormat( '!Y-m-d', $from, $timezone );
	$end = DateTimeImmutable::createFromFormat( '!Y-m-d', $to, $timezone );
	if ( ! $start || ! $end || $start->format( 'Y-m-d' ) !== $from || $end->format( 'Y-m-d' ) !== $to || $from > $to ) {
		return new WP_Error( 'parisan_invalid_report_range', __( 'بازه تاریخ گزارش معتبر نیست.', 'parisan-core' ) );
	}
	$utc = new DateTimeZone( 'UTC' );
	return array( $start->setTimezone( $utc )->format( 'Y-m-d H:i:s' ), $end->modify( '+1 day' )->setTimezone( $utc )->format( 'Y-m-d H:i:s' ) );
}

/** Fetch aggregated performance rows scoped to specialists editable by this user. */
function parisan_core_get_booking_report_rows( $from, $to, $staff_filter = 0, $status_filter = '' ) {
	global $wpdb;
	$range = parisan_core_booking_report_range( $from, $to );
	if ( is_wp_error( $range ) ) { return $range; }
	$staff_ids = get_posts( array( 'post_type'=>'parisan_staff', 'post_status'=>array('publish','draft','private'), 'posts_per_page'=>-1, 'fields'=>'ids', 'no_found_rows'=>true, 'perm'=>'editable' ) );
	$staff_ids = array_values( array_filter( array_map( 'absint', $staff_ids ), static function( $id ) { return current_user_can( 'edit_post', $id ); } ) );
	if ( $staff_filter && ! in_array( $staff_filter, $staff_ids, true ) ) { return new WP_Error( 'parisan_report_forbidden_staff', __( 'به گزارش این متخصص دسترسی ندارید.', 'parisan-core' ) ); }
	if ( ! $staff_ids ) { return array(); }
	$ids = $staff_filter ? array( $staff_filter ) : $staff_ids;
	$ids_sql = implode( ',', array_map( 'absint', $ids ) );
	$table = $wpdb->prefix . 'parisan_bookings';
	$sql = "SELECT b.staff_id, staff.post_title AS staff_name, b.service_id, service.post_title AS service_name, b.status, COUNT(*) AS booking_count, SUM(b.duration_minutes) AS duration_minutes, SUM(CASE WHEN b.status='completed' THEN b.price_amount ELSE 0 END) AS completed_value_irr
		FROM {$table} b
		LEFT JOIN {$wpdb->posts} staff ON staff.ID=b.staff_id
		LEFT JOIN {$wpdb->posts} service ON service.ID=b.service_id
		WHERE b.staff_id IN ({$ids_sql}) AND b.starts_at_utc >= %s AND b.starts_at_utc < %s";
	$args = array( $range[0], $range[1] );
	$allowed = array( 'pending','confirmed','cancelled','expired','completed','no_show' );
	if ( $status_filter && in_array( $status_filter, $allowed, true ) ) { $sql .= ' AND b.status=%s'; $args[] = $status_filter; }
	$sql .= ' GROUP BY b.staff_id, staff.post_title, b.service_id, service.post_title, b.status ORDER BY staff.post_title ASC, service.post_title ASC, b.status ASC';
	return $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
}

/** Prevent spreadsheet formula execution for untrusted text cells. */
function parisan_core_booking_report_csv_cell( $value ) {
	$value = (string) $value;
	return preg_match( '/^[=+@\-\t\r]/', $value ) ? "'" . $value : $value;
}

/** Stream the current user's scoped operational report as UTF-8 CSV. */
function parisan_core_handle_booking_report_csv() {
	if ( ! current_user_can( 'edit_others_posts' ) ) { wp_die( esc_html__( 'اجازه دریافت گزارش را ندارید.', 'parisan-core' ), '', array( 'response'=>403 ) ); }
	check_admin_referer( 'parisan_booking_report_csv' );
	$from = isset( $_GET['from'] ) && is_string( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
	$to = isset( $_GET['to'] ) && is_string( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
	$staff = isset( $_GET['staff_id'] ) ? absint( $_GET['staff_id'] ) : 0;
	$status = isset( $_GET['status'] ) && is_string( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
	$rows = parisan_core_get_booking_report_rows( $from, $to, $staff, $status );
	if ( is_wp_error( $rows ) ) { wp_die( esc_html( $rows->get_error_message() ), '', array( 'response'=>400 ) ); }
	 nocache_headers();
	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename=parisan-booking-report-' . gmdate( 'Ymd-His' ) . '.csv' );
	$output = fopen( 'php://output', 'w' );
	fwrite( $output, "\xEF\xBB\xBF" );
	fputcsv( $output, array( 'متخصص', 'خدمت', 'وضعیت', 'تعداد نوبت', 'مدت کل (دقیقه)', 'مبلغ نوبت‌های انجام‌شده (ریال)' ) );
	foreach ( $rows as $row ) {
		fputcsv( $output, array_map( 'parisan_core_booking_report_csv_cell', array( $row['staff_name'], $row['service_name'], $row['status'], $row['booking_count'], $row['duration_minutes'], $row['completed_value_irr'] ) ) );
	}
	fclose( $output );
	exit;
}
add_action( 'admin_post_parisan_booking_report_csv', 'parisan_core_handle_booking_report_csv' );

/** Render date/status/specialist filters and aggregate operational performance. */
function parisan_core_render_booking_report_page() {
	if ( ! current_user_can( 'edit_others_posts' ) ) { wp_die( esc_html__( 'اجازه مشاهده گزارش را ندارید.', 'parisan-core' ), '', array( 'response'=>403 ) ); }
	$today = current_datetime()->format( 'Y-m-d' );
	$from_default = current_datetime()->modify( 'first day of this month' )->format( 'Y-m-d' );
	$from = isset( $_GET['from'] ) && is_string( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : $from_default;
	$to = isset( $_GET['to'] ) && is_string( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : $today;
	$staff = isset( $_GET['staff_id'] ) ? absint( $_GET['staff_id'] ) : 0;
	$status = isset( $_GET['status'] ) && is_string( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
	$rows = parisan_core_get_booking_report_rows( $from, $to, $staff, $status );
	$staff_options = get_posts( array( 'post_type'=>'parisan_staff', 'post_status'=>array('publish','draft','private'), 'posts_per_page'=>-1, 'fields'=>'ids', 'no_found_rows'=>true, 'perm'=>'editable' ) );
	$staff_options = array_values( array_filter( array_map( 'absint', $staff_options ), static function( $id ) { return current_user_can( 'edit_post', $id ); } ) );
	$labels = array( 'pending'=>'در انتظار تأیید', 'confirmed'=>'تأییدشده', 'cancelled'=>'لغوشده', 'expired'=>'منقضی‌شده', 'completed'=>'انجام‌شده', 'no_show'=>'عدم مراجعه' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'گزارش عملکرد نوبت‌ها', 'parisan-core' ); ?></h1>
		<p><?php esc_html_e( 'گزارش بر اساس زمان شروع نوبت و منطقه زمانی سالن تهیه می‌شود. مبلغ فقط مجموع قیمت ثبت‌شده نوبت‌های انجام‌شده است و به معنی وجه نقد دریافت‌شده یا سود نیست.', 'parisan-core' ); ?></p>
		<form method="get"><input type="hidden" name="post_type" value="parisan_staff"><input type="hidden" name="page" value="parisan-booking-report">
			<label for="parisan-report-from"><?php esc_html_e( 'از تاریخ', 'parisan-core' ); ?></label> <input required type="date" id="parisan-report-from" name="from" value="<?php echo esc_attr( $from ); ?>">
			<label for="parisan-report-to"><?php esc_html_e( 'تا تاریخ', 'parisan-core' ); ?></label> <input required type="date" id="parisan-report-to" name="to" value="<?php echo esc_attr( $to ); ?>">
			<label for="parisan-report-staff"><?php esc_html_e( 'متخصص', 'parisan-core' ); ?></label> <select id="parisan-report-staff" name="staff_id"><option value="0"><?php esc_html_e( 'همه متخصصان مجاز', 'parisan-core' ); ?></option><?php foreach ( $staff_options as $id ) : ?><option value="<?php echo esc_attr( $id ); ?>" <?php selected( $staff, $id ); ?>><?php echo esc_html( get_the_title( $id ) ); ?></option><?php endforeach; ?></select>
			<label for="parisan-report-status"><?php esc_html_e( 'وضعیت', 'parisan-core' ); ?></label> <select id="parisan-report-status" name="status"><option value=""><?php esc_html_e( 'همه وضعیت‌ها', 'parisan-core' ); ?></option><?php foreach ( $labels as $key=>$label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
			<button class="button button-primary"><?php esc_html_e( 'تهیه گزارش', 'parisan-core' ); ?></button>
		</form>
		<?php if ( is_wp_error( $rows ) ) : ?><div class="notice notice-error"><p><?php echo esc_html( $rows->get_error_message() ); ?></p></div>
		<?php else : ?>
			<p><?php printf( esc_html__( 'تعداد ردیف‌های تجمیعی: %d', 'parisan-core' ), count( $rows ) ); ?></p>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="parisan_booking_report_csv"><input type="hidden" name="from" value="<?php echo esc_attr( $from ); ?>"><input type="hidden" name="to" value="<?php echo esc_attr( $to ); ?>"><input type="hidden" name="staff_id" value="<?php echo esc_attr( $staff ); ?>"><input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>"><?php wp_nonce_field( 'parisan_booking_report_csv' ); ?><button class="button"><?php esc_html_e( 'دریافت CSV', 'parisan-core' ); ?></button></form>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'متخصص', 'parisan-core' ); ?></th><th><?php esc_html_e( 'خدمت', 'parisan-core' ); ?></th><th><?php esc_html_e( 'وضعیت', 'parisan-core' ); ?></th><th><?php esc_html_e( 'تعداد', 'parisan-core' ); ?></th><th><?php esc_html_e( 'مدت کل (دقیقه)', 'parisan-core' ); ?></th><th><?php esc_html_e( 'ارزش نوبت‌های انجام‌شده (ریال)', 'parisan-core' ); ?></th></tr></thead><tbody>
			<?php foreach ( $rows as $row ) : ?><tr><td><?php echo esc_html( $row['staff_name'] ); ?></td><td><?php echo esc_html( $row['service_name'] ); ?></td><td><?php echo esc_html( isset( $labels[ $row['status'] ] ) ? $labels[ $row['status'] ] : $row['status'] ); ?></td><td><?php echo esc_html( number_format_i18n( $row['booking_count'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['duration_minutes'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['completed_value_irr'] ) ); ?></td></tr><?php endforeach; ?>
			<?php if ( ! $rows ) : ?><tr><td colspan="6"><?php esc_html_e( 'داده‌ای برای این بازه و فیلترها وجود ندارد.', 'parisan-core' ); ?></td></tr><?php endif; ?>
			</tbody></table>
		<?php endif; ?>
	</div>
	<?php
}
