<?php
/**
 * Permission-scoped customer loyalty and retention insights.
 *
 * Points are an engagement score derived from completed visits, not money,
 * stored value, a discount entitlement, or proof of payment.
 *
 * @package ParisanCore
 */
defined( 'ABSPATH' ) || exit;

function parisan_core_register_customer_loyalty_page() {
	add_submenu_page( 'edit.php?post_type=parisan_staff', __( 'وفاداری و بازگشت مشتری', 'parisan-core' ), __( 'وفاداری مشتری', 'parisan-core' ), 'edit_others_posts', 'parisan-customer-loyalty', 'parisan_core_render_customer_loyalty_page' );
}
add_action( 'admin_menu', 'parisan_core_register_customer_loyalty_page' );

function parisan_core_render_customer_loyalty_page() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'اجازه مشاهده گزارش وفاداری مشتریان را ندارید.', 'parisan-core' ), '', array( 'response' => 403 ) );
	}
	global $wpdb;
	$staff_ids = get_posts( array( 'post_type' => 'parisan_staff', 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true, 'perm' => 'editable' ) );
	$staff_ids = array_values( array_filter( array_map( 'absint', $staff_ids ), static function ( $id ) { return current_user_can( 'edit_post', $id ); } ) );
	$ids = $staff_ids ? implode( ',', $staff_ids ) : '0';
	$segment = isset( $_GET['segment'] ) && is_string( $_GET['segment'] ) ? sanitize_key( wp_unslash( $_GET['segment'] ) ) : 'all';
	if ( ! in_array( $segment, array( 'all', 'inactive', 'repeat', 'loyal', 'vip' ), true ) ) {
		$segment = 'all';
	}
	$search = isset( $_GET['customer_search'] ) && is_string( $_GET['customer_search'] ) ? sanitize_text_field( wp_unslash( $_GET['customer_search'] ) ) : '';
	$table = $wpdb->prefix . 'parisan_bookings';
	$rows = $wpdb->get_results(
		"SELECT b.customer_phone, MAX(b.customer_name) AS customer_name, MAX(b.customer_email) AS customer_email,
		COUNT(DISTINCT b.id) AS total_visits,
		SUM(CASE WHEN b.status='completed' THEN 1 ELSE 0 END) AS completed_visits,
		COALESCE(SUM(CASE WHEN b.status='completed' THEN b.price_amount ELSE 0 END),0) AS completed_service_value_irr,
		MAX(CASE WHEN b.status='completed' THEN b.starts_at_utc ELSE NULL END) AS last_completed_at,
		MAX(b.starts_at_utc) AS last_booking_at
		FROM {$table} b WHERE b.staff_id IN ({$ids}) AND b.customer_phone<>'' GROUP BY b.customer_phone ORDER BY last_booking_at DESC LIMIT 500",
		ARRAY_A
	);
	$visible = array();
	$cutoff = current_datetime()->modify( '-60 days' )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	foreach ( (array) $rows as $row ) {
		$completed = (int) $row['completed_visits'];
		$points = $completed * 10;
		if ( $completed >= 15 ) { $tier = 'ویژه'; }
		elseif ( $completed >= 8 ) { $tier = 'وفادار'; }
		elseif ( $completed >= 3 ) { $tier = 'همراه'; }
		elseif ( $completed >= 1 ) { $tier = 'تازه‌مراجعه‌کرده'; }
		else { $tier = 'بدون مراجعه تکمیل‌شده'; }
		$inactive = ! empty( $row['last_completed_at'] ) && $row['last_completed_at'] < $cutoff;
		if ( 'inactive' === $segment && ! $inactive ) { continue; }
		if ( 'repeat' === $segment && $completed < 2 ) { continue; }
		if ( 'loyal' === $segment && ( $completed < 8 || $completed >= 15 ) ) { continue; }
		if ( 'vip' === $segment && $completed < 15 ) { continue; }
		if ( '' !== $search && false === stripos( $row['customer_name'] . ' ' . $row['customer_phone'] . ' ' . $row['customer_email'], $search ) ) { continue; }
		$row['points'] = $points;
		$row['tier'] = $tier;
		$row['inactive'] = $inactive;
		$visible[] = $row;
	}
	echo '<div class="wrap"><h1>وفاداری و بازگشت مشتری</h1><p>امتیازها صرفاً شاخص تعامل هستند: هر مراجعه تکمیل‌شده ۱۰ امتیاز دارد. این امتیازها ارزش نقدی، اعتبار قابل‌خرج، تخفیف یا استحقاق بازپرداخت ایجاد نمی‌کنند و فقط از سوابق رزرو تکمیل‌شده محاسبه می‌شوند.</p>';
	echo '<form method="get"><input type="hidden" name="post_type" value="parisan_staff"><input type="hidden" name="page" value="parisan-customer-loyalty"><label>بخش مشتریان <select name="segment"><option value="all"' . selected( $segment, 'all', false ) . '>همه</option><option value="inactive"' . selected( $segment, 'inactive', false ) . '>بدون مراجعه تکمیل‌شده در ۶۰ روز اخیر</option><option value="repeat"' . selected( $segment, 'repeat', false ) . '>مشتریان تکراری</option><option value="loyal"' . selected( $segment, 'loyal', false ) . '>وفادار</option><option value="vip"' . selected( $segment, 'vip', false ) . '>ویژه</option></select></label> <label>جستجو <input type="search" name="customer_search" value="' . esc_attr( $search ) . '"></label> <button class="button button-primary">اعمال فیلتر</button></form>';
	echo '<p>' . esc_html( number_format_i18n( count( $visible ) ) ) . ' مشتری در این بخش</p><table class="widefat striped"><thead><tr><th>مشتری</th><th>تلفن</th><th>مراجعه تکمیل‌شده</th><th>امتیاز تعامل</th><th>سطح</th><th>ارزش خدمات تکمیل‌شده</th><th>آخرین مراجعه تکمیل‌شده</th><th>وضعیت بازگشت</th></tr></thead><tbody>';
	foreach ( $visible as $row ) {
		$last = $row['last_completed_at'] ? ( new DateTimeImmutable( $row['last_completed_at'], new DateTimeZone( 'UTC' ) ) )->setTimezone( wp_timezone() ) : null;
		echo '<tr><td>' . esc_html( $row['customer_name'] ) . '</td><td dir="ltr">' . esc_html( $row['customer_phone'] ) . '</td><td>' . esc_html( number_format_i18n( (int) $row['completed_visits'] ) ) . '</td><td>' . esc_html( number_format_i18n( (int) $row['points'] ) ) . '</td><td>' . esc_html( $row['tier'] ) . '</td><td>' . esc_html( number_format_i18n( intdiv( (int) $row['completed_service_value_irr'], 10 ) ) ) . ' تومان</td><td>' . esc_html( $last ? wp_date( 'Y/m/d', $last->getTimestamp(), wp_timezone() ) : '—' ) . '</td><td>' . esc_html( $row['inactive'] ? 'نیازمند پیگیری' : ( (int) $row['completed_visits'] >= 2 ? 'مشتری بازگشتی' : 'در حال آشنایی' ) ) . '</td></tr>';
	}
	if ( ! $visible ) { echo '<tr><td colspan="8">مشتری منطبق با این فیلتر پیدا نشد.</td></tr>'; }
	echo '</tbody></table><p>سطوح بر اساس تعداد مراجعه تکمیل‌شده محاسبه می‌شوند: همراه ۳ تا ۷، وفادار ۸ تا ۱۴ و ویژه ۱۵ مراجعه به بالا. مشتریان فاقد شماره تماس در گروه‌بندی قرار نمی‌گیرند. گزارش به متخصصان قابل‌ویرایش کاربر جاری محدود است.</p></div>';
}
