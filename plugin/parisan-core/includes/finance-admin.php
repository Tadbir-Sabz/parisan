<?php
/**
 * Read-only salon financial operations dashboard.
 *
 * Revenue is recognized from completed bookings only. Pending reservations,
 * cancellations, refunds, expenses and gateway settlements are not treated
 * as cash movements because no corresponding ledger/payment records exist.
 *
 * @package ParisanCore
 */
defined( 'ABSPATH' ) || exit;

/** Register the financial overview under the specialist workspace. */
function parisan_core_register_finance_admin_page() {
	add_submenu_page( 'edit.php?post_type=parisan_staff', __( 'گزارش مالی سالن', 'parisan-core' ), __( 'گزارش مالی', 'parisan-core' ), 'edit_others_posts', 'parisan-finance', 'parisan_core_render_finance_admin_page' );
}
add_action( 'admin_menu', 'parisan_core_register_finance_admin_page' );

/** Normalize a date filter without accepting relative or malformed dates. */
function parisan_core_finance_date( $value, $fallback ) {
	if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $value ) ) {
		return $fallback;
	}
	$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, wp_timezone() );
	return $date && $date->format( 'Y-m-d' ) === $value ? $value : $fallback;
}

/** Convert a salon-local inclusive date range into UTC SQL boundaries. */
function parisan_core_finance_range( $from, $to ) {
	$tz = wp_timezone();
	$start = new DateTimeImmutable( $from . ' 00:00:00', $tz );
	$end = ( new DateTimeImmutable( $to . ' 00:00:00', $tz ) )->modify( '+1 day' );
	$utc = new DateTimeZone( 'UTC' );
	return array( $start->setTimezone( $utc )->format( 'Y-m-d H:i:s' ), $end->setTimezone( $utc )->format( 'Y-m-d H:i:s' ) );
}

/** Render read-only revenue and operational performance reports. */
function parisan_core_render_finance_admin_page() {
	global $wpdb;
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'اجازه مشاهده گزارش مالی را ندارید.', 'parisan-core' ), '', array( 'response' => 403 ) );
	}
	$table = $wpdb->prefix . 'parisan_bookings';
	$today = current_datetime()->format( 'Y-m-d' );
	$default_from = current_datetime()->modify( 'first day of this month' )->format( 'Y-m-d' );
	$from = isset( $_GET['finance_from'] ) ? parisan_core_finance_date( sanitize_text_field( wp_unslash( $_GET['finance_from'] ) ), $default_from ) : $default_from;
	$to = isset( $_GET['finance_to'] ) ? parisan_core_finance_date( sanitize_text_field( wp_unslash( $_GET['finance_to'] ) ), $today ) : $today;
	if ( $from > $to ) {
		$swap = $from; $from = $to; $to = $swap;
	}
	$range = parisan_core_finance_range( $from, $to );
	$staff_ids = get_posts( array( 'post_type' => 'parisan_staff', 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true, 'perm' => 'editable' ) );
	$staff_ids = array_values( array_filter( array_map( 'absint', $staff_ids ), static function( $id ) { return current_user_can( 'edit_post', $id ); } ) );
	if ( ! $staff_ids ) {
		echo '<div class="wrap"><h1>' . esc_html__( 'گزارش مالی سالن', 'parisan-core' ) . '</h1><p>' . esc_html__( 'برای حساب کاربری شما متخصص قابل مدیریتی ثبت نشده است.', 'parisan-core' ) . '</p></div>';
		return;
	}
	$ids_sql = implode( ',', $staff_ids );
	$summary = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS booking_count, SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed_count, SUM(CASE WHEN status IN ('pending','confirmed') THEN 1 ELSE 0 END) AS open_count, SUM(CASE WHEN status IN ('cancelled','expired') THEN 1 ELSE 0 END) AS cancelled_count, COALESCE(SUM(CASE WHEN status='completed' THEN price_amount ELSE 0 END),0) AS recognized_irr, COALESCE(SUM(CASE WHEN status IN ('pending','confirmed') THEN price_amount ELSE 0 END),0) AS pipeline_irr FROM {$table} WHERE staff_id IN ({$ids_sql}) AND starts_at_utc >= %s AND starts_at_utc < %s", $range[0], $range[1] ), ARRAY_A );
	$services = $wpdb->get_results( $wpdb->prepare( "SELECT service_id, COUNT(*) AS booking_count, SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed_count, COALESCE(SUM(CASE WHEN status='completed' THEN price_amount ELSE 0 END),0) AS revenue_irr FROM {$table} WHERE staff_id IN ({$ids_sql}) AND starts_at_utc >= %s AND starts_at_utc < %s GROUP BY service_id ORDER BY revenue_irr DESC, booking_count DESC LIMIT 20", $range[0], $range[1] ), ARRAY_A );
	$staff = $wpdb->get_results( $wpdb->prepare( "SELECT staff_id, COUNT(*) AS booking_count, SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed_count, COALESCE(SUM(CASE WHEN status='completed' THEN price_amount ELSE 0 END),0) AS revenue_irr FROM {$table} WHERE staff_id IN ({$ids_sql}) AND starts_at_utc >= %s AND starts_at_utc < %s GROUP BY staff_id ORDER BY revenue_irr DESC, booking_count DESC", $range[0], $range[1] ), ARRAY_A );
	$summary = is_array( $summary ) ? $summary : array();
	$money = static function( $irr ) { return number_format_i18n( (int) round( (float) $irr / 10 ) ) . ' تومان'; };
	echo '<div class="wrap parisan-finance"><h1>' . esc_html__( 'گزارش مالی سالن', 'parisan-core' ) . '</h1>';
	echo '<p>' . esc_html__( 'گزارش بر اساس زمان شروع رزرو و متخصصانی است که امکان مدیریت آن‌ها را دارید.', 'parisan-core' ) . '</p>';
	echo '<p><a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=parisan_staff&page=parisan-finance-ledger' ) ) . '">دفتر ثبت درآمد و هزینه</a></p>';
	echo '<form method="get" class="parisan-finance-filters"><input type="hidden" name="post_type" value="parisan_staff"><input type="hidden" name="page" value="parisan-finance"><label>' . esc_html__( 'از تاریخ', 'parisan-core' ) . ' <input type="date" name="finance_from" value="' . esc_attr( $from ) . '"></label><label>' . esc_html__( 'تا تاریخ', 'parisan-core' ) . ' <input type="date" name="finance_to" value="' . esc_attr( $to ) . '"></label><button class="button button-primary">' . esc_html__( 'اعمال بازه', 'parisan-core' ) . '</button></form>';
	echo '<div class="parisan-finance-grid">';
	$cards = array(
		__( 'درآمد شناسایی‌شده', 'parisan-core' ) => $money( $summary['recognized_irr'] ?? 0 ),
		__( 'رزروهای تکمیل‌شده', 'parisan-core' ) => number_format_i18n( (int) ( $summary['completed_count'] ?? 0 ) ),
		__( 'رزروهای باز', 'parisan-core' ) => number_format_i18n( (int) ( $summary['open_count'] ?? 0 ) ),
		__( 'لغو یا منقضی‌شده', 'parisan-core' ) => number_format_i18n( (int) ( $summary['cancelled_count'] ?? 0 ) ),
		__( 'ارزش رزروهای باز (غیرقطعی)', 'parisan-core' ) => $money( $summary['pipeline_irr'] ?? 0 ),
	);
	foreach ( $cards as $label => $value ) { echo '<section class="parisan-finance-card"><h2>' . esc_html( $label ) . '</h2><strong>' . esc_html( $value ) . '</strong></section>'; }
	echo '</div><h2>' . esc_html__( 'عملکرد خدمات', 'parisan-core' ) . '</h2><table class="widefat striped"><thead><tr><th>خدمت</th><th>کل رزرو</th><th>تکمیل‌شده</th><th>درآمد</th></tr></thead><tbody>';
	foreach ( (array) $services as $row ) { echo '<tr><td>' . esc_html( get_the_title( (int) $row['service_id'] ) ?: __( 'خدمت حذف‌شده', 'parisan-core' ) ) . '</td><td>' . esc_html( number_format_i18n( (int) $row['booking_count'] ) ) . '</td><td>' . esc_html( number_format_i18n( (int) $row['completed_count'] ) ) . '</td><td>' . esc_html( $money( $row['revenue_irr'] ) ) . '</td></tr>'; }
	if ( ! $services ) { echo '<tr><td colspan="4">' . esc_html__( 'در این بازه رزروی ثبت نشده است.', 'parisan-core' ) . '</td></tr>'; }
	echo '</tbody></table><h2>' . esc_html__( 'عملکرد متخصصان', 'parisan-core' ) . '</h2><table class="widefat striped"><thead><tr><th>متخصص</th><th>کل رزرو</th><th>تکمیل‌شده</th><th>درآمد</th></tr></thead><tbody>';
	foreach ( (array) $staff as $row ) { echo '<tr><td>' . esc_html( get_the_title( (int) $row['staff_id'] ) ?: __( 'متخصص حذف‌شده', 'parisan-core' ) ) . '</td><td>' . esc_html( number_format_i18n( (int) $row['booking_count'] ) ) . '</td><td>' . esc_html( number_format_i18n( (int) $row['completed_count'] ) ) . '</td><td>' . esc_html( $money( $row['revenue_irr'] ) ) . '</td></tr>'; }
	if ( ! $staff ) { echo '<tr><td colspan="4">' . esc_html__( 'داده‌ای برای نمایش وجود ندارد.', 'parisan-core' ) . '</td></tr>'; }
	echo '</tbody></table><div class="notice notice-info inline"><p>' . esc_html__( 'درآمد فقط از رزروهای تکمیل‌شده محاسبه می‌شود. رزروهای باز صرفاً ارزش بالقوه‌اند؛ هزینه‌ها، تخفیف‌ها، استرداد، دریافت وجه و تسویه در سامانه دفترکل/پرداخت ثبت نمی‌شوند و این گزارش معادل جریان نقدی یا سود خالص نیست.', 'parisan-core' ) . '</p></div></div>';
	// Styles are enqueued on admin_enqueue_scripts, before the document head renders.
}
function parisan_core_enqueue_finance_admin_assets( $hook_suffix ) {
	if ( false === strpos( (string) $hook_suffix, 'parisan-finance' ) ) { return; }
	wp_register_style( 'parisan-finance-admin', false, array(), PARISAN_CORE_VERSION );
	wp_enqueue_style( 'parisan-finance-admin' );
	wp_add_inline_style( 'parisan-finance-admin', '.parisan-finance-filters{display:flex;gap:14px;align-items:end;flex-wrap:wrap;margin:18px 0}.parisan-finance-filters label{display:grid;gap:5px}.parisan-finance-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin:18px 0 26px}.parisan-finance-card{background:#fff;border:1px solid #dcdcde;border-radius:9px;padding:16px}.parisan-finance-card h2{font-size:13px;color:#646970;margin:0 0 10px}.parisan-finance-card strong{font-size:22px;color:#1d2327}.parisan-finance table{margin:12px 0 24px}' );
}
add_action( 'admin_enqueue_scripts', 'parisan_core_enqueue_finance_admin_assets' );

