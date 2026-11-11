<?php
/** Finance adjustments, period statement and CSV export. @package ParisanCore */
defined( 'ABSPATH' ) || exit;

function parisan_core_register_finance_operations_page() {
	add_submenu_page( 'edit.php?post_type=parisan_staff', __( 'گزارش دفتر مالی', 'parisan-core' ), __( 'گزارش دفتر مالی', 'parisan-core' ), 'manage_options', 'parisan-finance-operations', 'parisan_core_render_finance_operations_page' );
}
add_action( 'admin_menu', 'parisan_core_register_finance_operations_page' );

function parisan_core_handle_finance_adjustment() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'اجازه ثبت اصلاحیه مالی را ندارید.', 'parisan-core' ), '', array( 'response' => 403 ) ); }
	check_admin_referer( 'parisan_finance_adjustment', 'parisan_adjustment_nonce' );
	$entry_id = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;
	$kind = isset( $_POST['adjustment_kind'] ) && is_string( $_POST['adjustment_kind'] ) ? sanitize_key( wp_unslash( $_POST['adjustment_kind'] ) ) : '';
	$amount = isset( $_POST['amount_toman'] ) ? absint( wp_unslash( $_POST['amount_toman'] ) ) : 0;
	$reason = isset( $_POST['reason'] ) && is_string( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';
	if ( ! $entry_id || ! in_array( $kind, array( 'increase', 'decrease' ), true ) || $amount < 1 || $amount > 999999999999 || '' === $reason || strlen( $reason ) > 1500 ) { wp_die( esc_html__( 'اطلاعات اصلاحیه معتبر نیست.', 'parisan-core' ), '', array( 'response' => 400 ) ); }
	global $wpdb;
	$entries = $wpdb->prefix . 'parisan_finance_entries';
	$original = $wpdb->get_row( $wpdb->prepare( "SELECT id, entry_type FROM {$entries} WHERE id=%d", $entry_id ), ARRAY_A );
	if ( ! $original ) { wp_die( esc_html__( 'سند مبنا پیدا نشد.', 'parisan-core' ), '', array( 'response' => 404 ) ); }
	$ok = $wpdb->insert( $wpdb->prefix . 'parisan_finance_adjustments', array( 'entry_id'=>$entry_id, 'adjustment_kind'=>$kind, 'amount_irr'=>$amount * 10, 'reason'=>$reason, 'created_by'=>get_current_user_id(), 'created_at_utc'=>current_time( 'mysql', true ) ), array( '%d','%s','%d','%s','%d','%s' ) );
	wp_safe_redirect( add_query_arg( array( 'post_type'=>'parisan_staff','page'=>'parisan-finance-operations', ( $ok ? 'adjusted' : 'error' )=>1 ), admin_url( 'edit.php' ) ) ); exit;
}
add_action( 'admin_post_parisan_finance_adjustment', 'parisan_core_handle_finance_adjustment' );

function parisan_core_handle_finance_csv_export() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'اجازه دریافت گزارش مالی را ندارید.', 'parisan-core' ), '', array( 'response'=>403 ) ); }
	check_admin_referer( 'parisan_finance_csv' );
	global $wpdb;
	$entries = $wpdb->prefix . 'parisan_finance_entries';
	$adjustments = $wpdb->prefix . 'parisan_finance_adjustments';
	$rows = $wpdb->get_results( "SELECT e.id,e.entry_type,e.category,e.description,e.amount_irr,e.occurred_at_utc,e.created_by,'entry' AS row_kind,0 AS source_id FROM {$entries} e UNION ALL SELECT a.id,CONCAT('adjustment_',a.adjustment_kind),e.category,a.reason,a.amount_irr,a.created_at_utc,a.created_by,'adjustment',a.entry_id FROM {$adjustments} a INNER JOIN {$entries} e ON e.id=a.entry_id ORDER BY occurred_at_utc DESC", ARRAY_A );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename=parisan-finance-ledger.csv' );
	$out = fopen( 'php://output', 'w' );
	fprintf( $out, chr(0xEF).chr(0xBB).chr(0xBF) );
	fputcsv( $out, array( 'record_type','record_id','source_entry_id','entry_type','category','description_or_reason','amount_irr','occurred_at_utc','created_by' ) );
	foreach ( (array) $rows as $row ) { fputcsv( $out, array( $row['row_kind'], $row['id'], $row['source_id'], $row['entry_type'], $row['category'], $row['description'], $row['amount_irr'], $row['occurred_at_utc'], $row['created_by'] ) ); }
	fclose( $out ); exit;
}
add_action( 'admin_post_parisan_finance_csv_export', 'parisan_core_handle_finance_csv_export' );

function parisan_core_render_finance_operations_page() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'اجازه مشاهده دفتر مالی را ندارید.', 'parisan-core' ), '', array( 'response'=>403 ) ); }
	global $wpdb;
	$entries = $wpdb->prefix . 'parisan_finance_entries';
	$adjustments = $wpdb->prefix . 'parisan_finance_adjustments';
	$totals = $wpdb->get_row( "SELECT COALESCE(SUM(CASE WHEN entry_type='income' THEN amount_irr ELSE 0 END),0) AS income_irr, COALESCE(SUM(CASE WHEN entry_type='expense' THEN amount_irr ELSE 0 END),0) AS expense_irr FROM {$entries}", ARRAY_A );
	$adj = $wpdb->get_row( "SELECT COALESCE(SUM(CASE WHEN a.adjustment_kind='increase' AND e.entry_type='income' THEN a.amount_irr WHEN a.adjustment_kind='decrease' AND e.entry_type='expense' THEN a.amount_irr ELSE 0 END),0) AS positive_irr, COALESCE(SUM(CASE WHEN a.adjustment_kind='decrease' AND e.entry_type='income' THEN a.amount_irr WHEN a.adjustment_kind='increase' AND e.entry_type='expense' THEN a.amount_irr ELSE 0 END),0) AS negative_irr FROM {$adjustments} a INNER JOIN {$entries} e ON e.id=a.entry_id", ARRAY_A );
	$income = (int) ($totals['income_irr'] ?? 0) + (int) ($adj['positive_irr'] ?? 0) - (int) ($adj['negative_irr'] ?? 0);
	$expense = (int) ($totals['expense_irr'] ?? 0) + (int) ($adj['negative_irr'] ?? 0) - (int) ($adj['positive_irr'] ?? 0);
	$recent = $wpdb->get_results( "SELECT e.id,e.entry_type,e.category,e.description,e.amount_irr,e.occurred_at_utc,COALESCE(SUM(CASE WHEN a.adjustment_kind='increase' THEN a.amount_irr ELSE -a.amount_irr END),0) AS net_adjustment FROM {$entries} e LEFT JOIN {$adjustments} a ON a.entry_id=e.id GROUP BY e.id ORDER BY e.occurred_at_utc DESC LIMIT 100", ARRAY_A );
	$export = wp_nonce_url( admin_url( 'admin-post.php?action=parisan_finance_csv_export' ), 'parisan_finance_csv' );
	echo '<div class="wrap"><h1>گزارش دفتر مالی</h1><p>این صورت‌حساب فقط دفتر دستی ثبت‌شده را جمع‌بندی می‌کند؛ درآمد رزروهای تکمیل‌شده در گزارش عملیاتی جداگانه نمایش داده می‌شود و بدون مدرک دریافت وجه با این دفتر ادغام نمی‌شود.</p>';
	echo '<p><strong>درآمد تعدیل‌شده:</strong> '.esc_html(number_format_i18n(intdiv($income,10))).' تومان　<strong>هزینه تعدیل‌شده:</strong> '.esc_html(number_format_i18n(intdiv($expense,10))).' تومان　<strong>مانده دفتر:</strong> '.esc_html(number_format_i18n(intdiv($income-$expense,10))).' تومان</p>';
	echo '<p><a class="button button-primary" href="'.esc_url($export).'">دریافت CSV دفتر و اصلاحیه‌ها</a></p><h2>ثبت اصلاحیه (بدون حذف سند اصلی)</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="parisan_finance_adjustment">';
	wp_nonce_field('parisan_finance_adjustment','parisan_adjustment_nonce');
	echo '<label>شناسه سند <input type="number" min="1" name="entry_id" required></label> <label>نوع اصلاح <select name="adjustment_kind"><option value="increase">افزایش مبلغ سند</option><option value="decrease">کاهش مبلغ سند</option></select></label> <label>مبلغ اصلاح (تومان) <input type="number" min="1" max="999999999999" name="amount_toman" required></label><p><label>علت اصلاح <textarea name="reason" maxlength="1500" required></textarea></label></p><button class="button button-primary">ثبت اصلاحیه غیرقابل‌حذف</button></form>';
	echo '<h2>اسناد اخیر</h2><table class="widefat striped"><thead><tr><th>شناسه</th><th>تاریخ UTC</th><th>نوع</th><th>دسته</th><th>شرح</th><th>مبلغ اولیه</th><th>خالص اصلاحیه</th></tr></thead><tbody>';
	foreach ((array)$recent as $row) { echo '<tr><td>'.esc_html($row['id']).'</td><td>'.esc_html($row['occurred_at_utc']).'</td><td>'.esc_html($row['entry_type']==='income'?'درآمد':'هزینه').'</td><td>'.esc_html($row['category']).'</td><td>'.esc_html($row['description']).'</td><td>'.esc_html(number_format_i18n(intdiv((int)$row['amount_irr'],10))).' تومان</td><td>'.esc_html(number_format_i18n(intdiv((int)$row['net_adjustment'],10))).' تومان</td></tr>'; }
	if (!$recent) { echo '<tr><td colspan="7">سندی ثبت نشده است.</td></tr>'; }
	echo '</tbody></table><p>این گزارش معادل سود و زیان حسابداری یا مانده بانکی نیست؛ ثبت هزینه/درآمد دستی و اصلاحیه‌هاست. برای جلوگیری از دوباره‌شماری، دریافتی رزرو و تسویه درگاه در این جمع لحاظ نشده است.</p></div>';
}
