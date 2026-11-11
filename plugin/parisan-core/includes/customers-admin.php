<?php
/** Customer directory and booking history. @package ParisanCore */
defined( 'ABSPATH' ) || exit;
function parisan_core_register_customer_admin_page() {
	add_submenu_page( 'edit.php?post_type=parisan_staff', __( 'مدیریت مشتریان', 'parisan-core' ), __( 'مدیریت مشتریان', 'parisan-core' ), 'edit_others_posts', 'parisan-customers', 'parisan_core_render_customer_admin_page' );
}
add_action( 'admin_menu', 'parisan_core_register_customer_admin_page' );
function parisan_core_render_customer_admin_page() {
	global $wpdb;
	if ( ! current_user_can( 'edit_others_posts' ) ) { wp_die( esc_html__( 'اجازه مشاهده اطلاعات مشتریان را ندارید.', 'parisan-core' ), 403 ); }
	$table = $wpdb->prefix . 'parisan_bookings';
	$staff_ids = get_posts( array( 'post_type' => 'parisan_staff', 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => 500, 'fields' => 'ids', 'no_found_rows' => true ) );
	$staff_ids = array_values( array_filter( array_map( 'absint', $staff_ids ), static function ( $id ) { return current_user_can( 'edit_post', $id ); } ) );
	if ( ! $staff_ids ) { echo '<p>' . esc_html__( 'برای مشاهده مشتریان به دسترسی مدیریت حداقل یک متخصص نیاز دارید.', 'parisan-core' ) . '</p></div>'; return; }
	$staff_placeholders = implode( ',', array_fill( 0, count( $staff_ids ), '%d' ) );
	$phone = isset( $_GET['customer_phone'] ) && is_string( $_GET['customer_phone'] ) ? sanitize_text_field( wp_unslash( $_GET['customer_phone'] ) ) : '';
	$search = isset( $_GET['customer_search'] ) && is_string( $_GET['customer_search'] ) ? sanitize_text_field( wp_unslash( $_GET['customer_search'] ) ) : '';
	$labels = array( 'pending' => 'در انتظار تأیید', 'confirmed' => 'تأییدشده', 'cancelled' => 'لغوشده', 'expired' => 'منقضی‌شده', 'completed' => 'انجام‌شده', 'no_show' => 'عدم مراجعه' );
	echo '<div class="wrap"><h1>' . esc_html__( 'مدیریت مشتریان', 'parisan-core' ) . '</h1><p>' . esc_html__( 'نمایه مشتریان بر اساس سوابق رزرو؛ همراه با تعداد مراجعه، خدمات انجام‌شده، مبلغ ثبت‌شده و تاریخچه کامل.', 'parisan-core' ) . '</p>';
	if ( '' !== $phone ) {
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT b.*, s.post_title AS service_title, st.post_title AS staff_title FROM {$table} b LEFT JOIN {$wpdb->posts} s ON s.ID=b.service_id LEFT JOIN {$wpdb->posts} st ON st.ID=b.staff_id WHERE b.customer_phone=%s AND b.staff_id IN ({$staff_placeholders}) ORDER BY b.starts_at_utc DESC LIMIT 200", array_merge( array( $phone ), $staff_ids ) ), ARRAY_A );
		$rows = array_values( array_filter( (array) $rows, static function ( $row ) { return current_user_can( 'edit_post', (int) $row['staff_id'] ); } ) );
		echo '<p><a href="' . esc_url( add_query_arg( array( 'post_type' => 'parisan_staff', 'page' => 'parisan-customers' ), admin_url( 'edit.php' ) ) ) . '">' . esc_html__( 'بازگشت به فهرست مشتریان', 'parisan-core' ) . '</a></p>';
		echo '<h2>' . esc_html( $rows ? $rows[0]['customer_name'] : __( 'مشتری', 'parisan-core' ) ) . '</h2><p dir="ltr">' . esc_html( $phone ) . '</p>';
		if ( $rows ) {
			echo '<table class="widefat striped"><thead><tr><th>تاریخ</th><th>خدمت</th><th>متخصص</th><th>مبلغ</th><th>وضعیت</th><th>جزئیات</th></tr></thead><tbody>';
			foreach ( $rows as $row ) {
				$start = ( new DateTimeImmutable( $row['starts_at_utc'], new DateTimeZone( 'UTC' ) ) )->setTimezone( wp_timezone() );
				$url = add_query_arg( array( 'post_type' => 'parisan_staff', 'page' => 'parisan-bookings', 'booking_id' => (int) $row['id'] ), admin_url( 'edit.php' ) );
				echo '<tr><td>' . esc_html( wp_date( 'Y/m/d H:i', $start->getTimestamp(), wp_timezone() ) ) . '</td><td>' . esc_html( $row['service_title'] ? $row['service_title'] : '—' ) . '</td><td>' . esc_html( $row['staff_title'] ? $row['staff_title'] : '—' ) . '</td><td>' . esc_html( number_format_i18n( intdiv( (int) $row['price_amount'], 10 ) ) . ' تومان' ) . '</td><td>' . esc_html( isset( $labels[ $row['status'] ] ) ? $labels[ $row['status'] ] : $row['status'] ) . '</td><td><a href="' . esc_url( $url ) . '">مشاهده نوبت</a></td></tr>';
			}
			echo '</tbody></table>';
		} else { echo '<p>' . esc_html__( 'سابقه قابل مشاهده‌ای پیدا نشد.', 'parisan-core' ) . '</p>'; }
		echo '</div>'; return;
	}
	$where = ''; $args = array();
	if ( '' !== $search ) { $like = '%' . $wpdb->esc_like( $search ) . '%'; $where = ' WHERE staff_id IN (' . $staff_placeholders . ') AND (customer_name LIKE %s OR customer_phone LIKE %s OR customer_email LIKE %s)'; $args = array_merge( $staff_ids, array( $like, $like, $like ) ); }
	$scope = ' WHERE staff_id IN (' . $staff_placeholders . ') AND customer_phone <> %s';
	$scope_args = array_merge( $staff_ids, array( '' ) );
	$sql = "SELECT customer_phone, MAX(customer_name) customer_name, MAX(customer_email) customer_email, COUNT(*) visit_count, SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) completed_count, SUM(CASE WHEN status='completed' THEN price_amount ELSE 0 END) completed_total, MAX(starts_at_utc) last_visit FROM {$table}" . ( $where ? $where : $scope ) . ' GROUP BY customer_phone ORDER BY last_visit DESC LIMIT 300';
	if ( $where ) { $args = array_merge( $staff_ids, array_slice( $args, count( $staff_ids ) ) ); } else { $args = $scope_args; }
	$customers = $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql, ARRAY_A );
	$visible = array();
	$visible = (array) $customers;
	echo '<form method="get"><input type="hidden" name="post_type" value="parisan_staff"><input type="hidden" name="page" value="parisan-customers"><label for="parisan-customer-search">' . esc_html__( 'جستجوی نام، شماره تماس یا ایمیل', 'parisan-core' ) . '</label> <input id="parisan-customer-search" type="search" name="customer_search" value="' . esc_attr( $search ) . '"> <button class="button button-primary">جستجو</button></form>';
	echo '<p>' . esc_html( sprintf( __( '%s مشتری در نتایج', 'parisan-core' ), number_format_i18n( count( $visible ) ) ) ) . '</p>';
	if ( $visible ) {
		echo '<table class="widefat striped"><thead><tr><th>مشتری</th><th>شماره تماس</th><th>ایمیل</th><th>مراجعه</th><th>انجام‌شده</th><th>مجموع خدمات انجام‌شده</th><th>آخرین مراجعه</th><th></th></tr></thead><tbody>';
		foreach ( $visible as $customer ) {
			$last = ( new DateTimeImmutable( $customer['last_visit'], new DateTimeZone( 'UTC' ) ) )->setTimezone( wp_timezone() );
			$url = add_query_arg( array( 'post_type' => 'parisan_staff', 'page' => 'parisan-customers', 'customer_phone' => $customer['customer_phone'] ), admin_url( 'edit.php' ) );
			echo '<tr><td><strong>' . esc_html( $customer['customer_name'] ) . '</strong></td><td dir="ltr">' . esc_html( $customer['customer_phone'] ) . '</td><td>' . esc_html( $customer['customer_email'] ? $customer['customer_email'] : '—' ) . '</td><td>' . esc_html( number_format_i18n( (int) $customer['visit_count'] ) ) . '</td><td>' . esc_html( number_format_i18n( (int) $customer['completed_count'] ) ) . '</td><td>' . esc_html( number_format_i18n( intdiv( (int) $customer['completed_total'], 10 ) ) . ' تومان' ) . '</td><td>' . esc_html( wp_date( 'Y/m/d', $last->getTimestamp(), wp_timezone() ) ) . '</td><td><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'تاریخچه مشتری', 'parisan-core' ) . '</a></td></tr>';
		}
		echo '</tbody></table>';
	} else { echo '<p>' . esc_html__( 'هنوز سابقه‌ای برای نمایش وجود ندارد.', 'parisan-core' ) . '</p>'; }
	echo '</div>';
}
