<?php
/**
 * Booking-level invoice adjustments and manually recorded refund events.
 *
 * These records are operational declarations, not payment-gateway or bank
 * evidence. Original booking price snapshots and events remain immutable.
 *
 * @package ParisanCore
 */
defined( 'ABSPATH' ) || exit;

function parisan_core_register_booking_finance_page() {
	add_submenu_page( 'edit.php?post_type=parisan_staff', __( 'اصلاحات مالی رزروها', 'parisan-core' ), __( 'مالی رزروها', 'parisan-core' ), 'manage_options', 'parisan-booking-finance', 'parisan_core_render_booking_finance_page' );
}
add_action( 'admin_menu', 'parisan_core_register_booking_finance_page' );

function parisan_core_handle_booking_finance_event() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'اجازه ثبت رویداد مالی رزرو را ندارید.', 'parisan-core' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'parisan_booking_finance_event', 'parisan_booking_finance_nonce' );
	$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
	$kind = isset( $_POST['event_kind'] ) && is_string( $_POST['event_kind'] ) ? sanitize_key( wp_unslash( $_POST['event_kind'] ) ) : '';
	$toman = isset( $_POST['amount_toman'] ) ? absint( wp_unslash( $_POST['amount_toman'] ) ) : 0;
	$note = isset( $_POST['note'] ) && is_string( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
	if ( ! $booking_id || ! in_array( $kind, array( 'discount', 'surcharge', 'refund' ), true ) || $toman < 1 || $toman > 999999999999 || '' === $note || strlen( $note ) > 1500 ) {
		wp_die( esc_html__( 'اطلاعات رویداد مالی معتبر نیست.', 'parisan-core' ), '', array( 'response' => 400 ) );
	}
	global $wpdb;
	$bookings = $wpdb->prefix . 'parisan_bookings';
	$events = $wpdb->prefix . 'parisan_booking_finance_events';
	if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
		wp_die( esc_html__( 'تراکنش امن آغاز نشد؛ هیچ تغییری ثبت نشد.', 'parisan-core' ), '', array( 'response' => 503 ) );
	}
	$booking = $wpdb->get_row( $wpdb->prepare( "SELECT id, price_amount FROM {$bookings} WHERE id=%d FOR UPDATE", $booking_id ), ARRAY_A );
	if ( ! $booking ) {
		$wpdb->query( 'ROLLBACK' );
		wp_die( esc_html__( 'رزرو موردنظر پیدا نشد.', 'parisan-core' ), '', array( 'response' => 404 ) );
	}
	$totals = $wpdb->get_row( $wpdb->prepare(
		"SELECT COALESCE(SUM(CASE WHEN event_kind='discount' THEN amount_irr ELSE 0 END),0) AS discounts,
		COALESCE(SUM(CASE WHEN event_kind='surcharge' THEN amount_irr ELSE 0 END),0) AS surcharges,
		COALESCE(SUM(CASE WHEN event_kind='refund' THEN amount_irr ELSE 0 END),0) AS refunds
		FROM {$events} WHERE booking_id=%d",
		$booking_id
	), ARRAY_A );
	$base = (int) $booking['price_amount'];
	$discounts = (int) ( $totals['discounts'] ?? 0 );
	$surcharges = (int) ( $totals['surcharges'] ?? 0 );
	$refunds = (int) ( $totals['refunds'] ?? 0 );
	$amount = $toman * 10;
	$invoice_total = $base - $discounts + $surcharges;
	$invalid = ( 'discount' === $kind && ( $amount > $invoice_total || $amount > PHP_INT_MAX - $surcharges ) )
		|| ( 'surcharge' === $kind && $amount > PHP_INT_MAX - $invoice_total )
		|| ( 'refund' === $kind && ( $invoice_total < 0 || $amount > ( $invoice_total - $refunds ) ) );
	if ( $invalid || $amount < 1 ) {
		$wpdb->query( 'ROLLBACK' );
		wp_die( esc_html__( 'مبلغ رویداد از مانده قابل ثبت بیشتر است یا از محدوده مجاز خارج شده است.', 'parisan-core' ), '', array( 'response' => 400 ) );
	}
	$inserted = $wpdb->insert( $events, array(
		'booking_id' => $booking_id,
		'event_kind' => $kind,
		'amount_irr' => $amount,
		'note' => $note,
		'created_by' => get_current_user_id(),
		'created_at_utc' => current_time( 'mysql', true ),
	), array( '%d', '%s', '%d', '%s', '%d', '%s' ) );
	if ( false === $inserted ) {
		$wpdb->query( 'ROLLBACK' );
		wp_die( esc_html__( 'ثبت رویداد مالی ناموفق بود؛ تراکنش بازگردانی شد.', 'parisan-core' ), '', array( 'response' => 500 ) );
	}
	$wpdb->query( 'COMMIT' );
	wp_safe_redirect( add_query_arg( array( 'post_type' => 'parisan_staff', 'page' => 'parisan-booking-finance', 'booking_id' => $booking_id, 'saved' => 1 ), admin_url( 'edit.php' ) ) );
	exit;
}
add_action( 'admin_post_parisan_booking_finance_event', 'parisan_core_handle_booking_finance_event' );

function parisan_core_render_booking_finance_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'اجازه مشاهده اصلاحات مالی رزروها را ندارید.', 'parisan-core' ), '', array( 'response' => 403 ) );
	}
	global $wpdb;
	$bookings = $wpdb->prefix . 'parisan_bookings';
	$events = $wpdb->prefix . 'parisan_booking_finance_events';
	$booking_id = isset( $_GET['booking_id'] ) ? absint( $_GET['booking_id'] ) : 0;
	echo '<div class="wrap"><h1>اصلاحات مالی رزروها</h1><p>قیمت اولیه رزرو حفظ می‌شود. تخفیف و افزایش مبلغ، مبلغ صورتحساب اصلاح‌شده را می‌سازند؛ استرداد دستی جداگانه ثبت می‌شود و از صورتحساب کم نمی‌شود. این ثبت‌ها تأییدیه درگاه، بانک یا پرداخت واقعی نیستند.</p>';
	echo '<form method="get"><input type="hidden" name="post_type" value="parisan_staff"><input type="hidden" name="page" value="parisan-booking-finance"><label>شناسه رزرو <input type="number" min="1" name="booking_id" value="' . esc_attr( $booking_id ? $booking_id : '' ) . '" required></label> <button class="button">نمایش رزرو</button></form>';
	if ( $booking_id ) {
		$booking = $wpdb->get_row( $wpdb->prepare( "SELECT id,public_id,customer_name,customer_phone,service_id,staff_id,status,price_amount,currency_code,starts_at_utc FROM {$bookings} WHERE id=%d", $booking_id ), ARRAY_A );
		if ( ! $booking ) {
			echo '<p>رزرو پیدا نشد.</p></div>';
			return;
		}
		$totals = $wpdb->get_row( $wpdb->prepare( "SELECT COALESCE(SUM(CASE WHEN event_kind='discount' THEN amount_irr ELSE 0 END),0) discounts, COALESCE(SUM(CASE WHEN event_kind='surcharge' THEN amount_irr ELSE 0 END),0) surcharges, COALESCE(SUM(CASE WHEN event_kind='refund' THEN amount_irr ELSE 0 END),0) refunds FROM {$events} WHERE booking_id=%d", $booking_id ), ARRAY_A );
		$discounts = (int) $totals['discounts'];
		$surcharges = (int) $totals['surcharges'];
		$refunds = (int) $totals['refunds'];
		$invoice = (int) $booking['price_amount'] - $discounts + $surcharges;
		echo '<h2>رزرو #' . esc_html( $booking['id'] ) . ' — ' . esc_html( $booking['customer_name'] ) . '</h2><p>خدمت: ' . esc_html( get_the_title( (int) $booking['service_id'] ) ) . ' | وضعیت: ' . esc_html( $booking['status'] ) . '</p>';
		echo '<ul><li>قیمت اولیه: ' . esc_html( number_format_i18n( intdiv( (int) $booking['price_amount'], 10 ) ) ) . ' تومان</li><li>تخفیف ثبت‌شده: ' . esc_html( number_format_i18n( intdiv( $discounts, 10 ) ) ) . ' تومان</li><li>افزایش مبلغ: ' . esc_html( number_format_i18n( intdiv( $surcharges, 10 ) ) ) . ' تومان</li><li><strong>صورتحساب اصلاح‌شده: ' . esc_html( number_format_i18n( intdiv( max( 0, $invoice ), 10 ) ) ) . ' تومان</strong></li><li>استردادهای ثبت‌شده: ' . esc_html( number_format_i18n( intdiv( $refunds, 10 ) ) ) . ' تومان</li><li>مانده پس از استردادهای ثبت‌شده: ' . esc_html( number_format_i18n( intdiv( max( 0, $invoice - $refunds ), 10 ) ) ) . ' تومان</li></ul>';
		echo '<h3>ثبت رویداد جدید</h3><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="parisan_booking_finance_event"><input type="hidden" name="booking_id" value="' . esc_attr( $booking_id ) . '">';
		wp_nonce_field( 'parisan_booking_finance_event', 'parisan_booking_finance_nonce' );
		echo '<label>نوع رویداد <select name="event_kind"><option value="discount">تخفیف</option><option value="surcharge">افزایش مبلغ</option><option value="refund">ثبت دستی استرداد انجام‌شده</option></select></label> <label>مبلغ (تومان) <input type="number" name="amount_toman" min="1" max="999999999999" required></label><p><label>توضیح و مستند داخلی <textarea name="note" maxlength="1500" required></textarea></label></p><button class="button button-primary">ثبت رویداد غیرقابل‌حذف</button></form>';
		$history = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$events} WHERE booking_id=%d ORDER BY id DESC LIMIT 200", $booking_id ), ARRAY_A );
		echo '<h3>تاریخچه رویدادها</h3><table class="widefat striped"><thead><tr><th>نوع</th><th>مبلغ</th><th>شرح</th><th>ثبت‌کننده</th><th>زمان UTC</th></tr></thead><tbody>';
		$labels = array( 'discount' => 'تخفیف', 'surcharge' => 'افزایش مبلغ', 'refund' => 'استرداد دستی ثبت‌شده' );
		foreach ( (array) $history as $event ) {
			echo '<tr><td>' . esc_html( $labels[ $event['event_kind'] ] ?? $event['event_kind'] ) . '</td><td>' . esc_html( number_format_i18n( intdiv( (int) $event['amount_irr'], 10 ) ) ) . ' تومان</td><td>' . esc_html( $event['note'] ) . '</td><td>' . esc_html( get_the_author_meta( 'display_name', (int) $event['created_by'] ) ) . '</td><td>' . esc_html( $event['created_at_utc'] ) . '</td></tr>';
		}
		if ( ! $history ) { echo '<tr><td colspan="5">رویدادی ثبت نشده است.</td></tr>'; }
		echo '</tbody></table>';
	}
	$recent = $wpdb->get_results( "SELECT b.id,b.customer_name,b.status,b.price_amount,b.starts_at_utc,COUNT(e.id) event_count FROM {$bookings} b LEFT JOIN {$events} e ON e.booking_id=b.id GROUP BY b.id ORDER BY b.starts_at_utc DESC LIMIT 50", ARRAY_A );
	echo '<h2>رزروهای اخیر</h2><table class="widefat striped"><thead><tr><th>شناسه</th><th>مشتری</th><th>وضعیت</th><th>قیمت اولیه</th><th>رویدادها</th><th></th></tr></thead><tbody>';
	foreach ( (array) $recent as $row ) {
		$url = add_query_arg( array( 'post_type' => 'parisan_staff', 'page' => 'parisan-booking-finance', 'booking_id' => (int) $row['id'] ), admin_url( 'edit.php' ) );
		echo '<tr><td>' . esc_html( $row['id'] ) . '</td><td>' . esc_html( $row['customer_name'] ) . '</td><td>' . esc_html( $row['status'] ) . '</td><td>' . esc_html( number_format_i18n( intdiv( (int) $row['price_amount'], 10 ) ) ) . ' تومان</td><td>' . esc_html( $row['event_count'] ) . '</td><td><a class="button" href="' . esc_url( $url ) . '">مدیریت مالی</a></td></tr>';
	}
	echo '</tbody></table></div>';
}
