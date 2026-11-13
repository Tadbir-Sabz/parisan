<?php
/** Manual salon income/expense ledger. @package ParisanCore */
defined( 'ABSPATH' ) || exit;

function parisan_core_register_finance_ledger_page() {
	add_submenu_page( 'edit.php?post_type=parisan_staff', __( 'دفتر درآمد و هزینه', 'parisan-core' ), __( 'دفتر درآمد و هزینه', 'parisan-core' ), 'manage_options', 'parisan-finance-ledger', 'parisan_core_render_finance_ledger_page' );
}
add_action( 'admin_menu', 'parisan_core_register_finance_ledger_page' );

function parisan_core_handle_finance_entry_save() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'اجازه ثبت سند مالی را ندارید.', 'parisan-core' ), '', array( 'response' => 403 ) ); }
	check_admin_referer( 'parisan_save_finance_entry', 'parisan_finance_nonce' );
	global $wpdb;
	$type = isset( $_POST['entry_type'] ) && is_string( $_POST['entry_type'] ) ? sanitize_key( wp_unslash( $_POST['entry_type'] ) ) : '';
	$category = isset( $_POST['category'] ) && is_string( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
	$description = isset( $_POST['description'] ) && is_string( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
	$amount = isset( $_POST['amount_toman'] ) && is_scalar( $_POST['amount_toman'] ) ? absint( wp_unslash( $_POST['amount_toman'] ) ) : 0;
	$date = isset( $_POST['entry_date'] ) && is_string( $_POST['entry_date'] ) ? sanitize_text_field( wp_unslash( $_POST['entry_date'] ) ) : '';
	$staff_id = isset( $_POST['staff_id'] ) && is_scalar( $_POST['staff_id'] ) ? absint( wp_unslash( $_POST['staff_id'] ) ) : 0;
	$valid_date = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
	if ( ! in_array( $type, array( 'income', 'expense' ), true ) || '' === $category || strlen( $category ) > 100 || '' === $description || strlen( $description ) > 2000 || $amount < 1 || $amount > 999999999999 || ! $valid_date || $valid_date->format( 'Y-m-d' ) !== $date || ( $staff_id && ( 'parisan_staff' !== get_post_type( $staff_id ) || ! current_user_can( 'edit_post', $staff_id ) ) ) ) {
		wp_safe_redirect( add_query_arg( array( 'post_type' => 'parisan_staff', 'page' => 'parisan-finance-ledger', 'ledger_error' => 'invalid' ), admin_url( 'edit.php' ) ) ); exit;
	}
	$local = new DateTimeImmutable( $date . ' 12:00:00', wp_timezone() );
	$utc = $local->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	$table = $wpdb->prefix . 'parisan_finance_entries';
	$ok = $wpdb->insert( $table, array( 'entry_type' => $type, 'category' => $category, 'description' => $description, 'amount_irr' => $amount * 10, 'staff_id' => $staff_id, 'occurred_at_utc' => $utc, 'created_by' => get_current_user_id(), 'created_at_utc' => current_time( 'mysql', true ) ), array( '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s' ) );
	wp_safe_redirect( add_query_arg( array( 'post_type' => 'parisan_staff', 'page' => 'parisan-finance-ledger', ( $ok ? 'ledger_saved' : 'ledger_error' ) => $ok ? 1 : 'database' ), admin_url( 'edit.php' ) ) ); exit;
}
add_action( 'admin_post_parisan_save_finance_entry', 'parisan_core_handle_finance_entry_save' );

function parisan_core_render_finance_ledger_page() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'اجازه مشاهده دفتر مالی را ندارید.', 'parisan-core' ), '', array( 'response' => 403 ) ); }
	global $wpdb;
	$table = $wpdb->prefix . 'parisan_finance_entries';
	$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY occurred_at_utc DESC, id DESC LIMIT 200", ARRAY_A );
	$staff = get_posts( array( 'post_type' => 'parisan_staff', 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) );
	echo '<div class="wrap"><h1>' . esc_html__( 'دفتر درآمد و هزینه', 'parisan-core' ) . '</h1><p>' . esc_html__( 'ثبت دستی اسناد مالی سالن؛ این دفتر مستقل از تراکنش‌های درگاه پرداخت است.', 'parisan-core' ) . '</p>';
	if ( isset( $_GET['ledger_saved'] ) ) { echo '<div class="notice notice-success"><p>سند مالی ثبت شد.</p></div>'; }
	if ( isset( $_GET['ledger_error'] ) ) { echo '<div class="notice notice-error"><p>ثبت سند انجام نشد؛ اطلاعات را بررسی کنید.</p></div>'; }
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="parisan-ledger-form">';
	echo '<input type="hidden" name="action" value="parisan_save_finance_entry">'; wp_nonce_field( 'parisan_save_finance_entry', 'parisan_finance_nonce' );
	echo '<p><label>نوع سند <select name="entry_type" required><option value="income">درآمد دریافتی</option><option value="expense">هزینه پرداخت‌شده</option></select></label> ';
	echo '<label>دسته‌بندی <input name="category" maxlength="100" required placeholder="مثلاً فروش محصول، اجاره، مواد مصرفی"></label> ';
	echo '<label>مبلغ (تومان) <input name="amount_toman" type="number" min="1" max="999999999999" required></label> ';
	echo '<label>تاریخ <input name="entry_date" type="date" value="' . esc_attr( current_datetime()->format( 'Y-m-d' ) ) . '" required></label></p>';
	echo '<p><label>متخصص مرتبط (اختیاری) <select name="staff_id"><option value="0">کل سالن</option>';
	foreach ( $staff as $id ) { if ( current_user_can( 'edit_post', $id ) ) { echo '<option value="' . esc_attr( $id ) . '">' . esc_html( get_the_title( $id ) ) . '</option>'; } }
	echo '</select></label> <label>شرح سند <textarea name="description" rows="2" maxlength="2000" required></textarea></label></p><p><button class="button button-primary">ثبت سند مالی</button></p></form>';
	echo '<h2>آخرین اسناد (۲۰۰ مورد)</h2><table class="widefat striped"><thead><tr><th>تاریخ</th><th>نوع</th><th>دسته‌بندی</th><th>شرح</th><th>متخصص</th><th>مبلغ</th><th>ثبت‌کننده</th></tr></thead><tbody>';
	foreach ( (array) $rows as $row ) { $dt = new DateTimeImmutable( $row['occurred_at_utc'], new DateTimeZone( 'UTC' ) ); $dt = $dt->setTimezone( wp_timezone() ); $is_income = 'income' === $row['entry_type']; echo '<tr><td>' . esc_html( wp_date( 'Y/m/d', $dt->getTimestamp(), wp_timezone() ) ) . '</td><td>' . esc_html( $is_income ? 'درآمد' : 'هزینه' ) . '</td><td>' . esc_html( $row['category'] ) . '</td><td>' . esc_html( $row['description'] ) . '</td><td>' . esc_html( $row['staff_id'] ? get_the_title( (int) $row['staff_id'] ) : 'کل سالن' ) . '</td><td>' . esc_html( ( $is_income ? '+' : '−' ) . number_format_i18n( intdiv( (int) $row['amount_irr'], 10 ) ) . ' تومان' ) . '</td><td>' . esc_html( get_the_author_meta( 'display_name', (int) $row['created_by'] ) ) . '</td></tr>'; }
	if ( ! $rows ) { echo '<tr><td colspan="7">هنوز سند مالی ثبت نشده است.</td></tr>'; }
	echo '</tbody></table><div class="notice notice-warning inline"><p>اسناد ثبت‌شده، ورودی دستی دفتر مالی هستند و به‌تنهایی اثبات پرداخت بانکی یا اتصال به درگاه نیستند. حذف/ویرایش سند در این نسخه ارائه نشده تا سابقه ثبت‌شده بی‌ردپا تغییر نکند.</p></div></div>';
}
