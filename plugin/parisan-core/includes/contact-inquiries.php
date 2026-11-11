<?php
/** Public contact form and private inquiry inbox. @package ParisanCore */
defined( 'ABSPATH' ) || exit;

function parisan_core_render_contact_form() {
	$status = isset( $_GET['contact_status'] ) && is_string( $_GET['contact_status'] ) ? sanitize_key( wp_unslash( $_GET['contact_status'] ) ) : '';
	if ( 'sent' === $status ) { echo '<div class="notice notice-success" role="status"><p>پیام شما ثبت شد. در اولین فرصت با شما تماس می‌گیریم.</p></div>'; }
	elseif ( 'invalid' === $status ) { echo '<div class="notice notice-error" role="alert"><p>اطلاعات واردشده کامل یا معتبر نیست؛ لطفاً دوباره بررسی کنید.</p></div>'; }
	elseif ( 'limited' === $status ) { echo '<div class="notice notice-warning" role="alert"><p>تعداد درخواست‌ها بیش از حد مجاز است. کمی بعد دوباره تلاش کنید.</p></div>'; }
	echo '<form class="parisan-contact-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="parisan_submit_contact_inquiry">';
	wp_nonce_field( 'parisan_public_contact', 'parisan_contact_nonce' );
	echo '<div class="parisan-contact-honeypot" aria-hidden="true"><label>این قسمت را خالی بگذارید <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
	echo '<p><label for="parisan-contact-name">نام و نام خانوادگی</label><input id="parisan-contact-name" name="contact_name" type="text" maxlength="120" autocomplete="name" required></p>';
	echo '<p><label for="parisan-contact-phone">شماره تماس</label><input id="parisan-contact-phone" name="contact_phone" type="tel" maxlength="24" autocomplete="tel" required></p>';
	echo '<p><label for="parisan-contact-email">ایمیل (اختیاری)</label><input id="parisan-contact-email" name="contact_email" type="email" maxlength="190" autocomplete="email"></p>';
	echo '<p><label for="parisan-contact-subject">موضوع</label><select id="parisan-contact-subject" name="contact_subject" required><option value="">انتخاب کنید</option><option value="service">راهنمایی خدمات</option><option value="specialist">انتخاب متخصص</option><option value="appointment">هماهنگی مراجعه</option><option value="other">سایر</option></select></p>';
	echo '<p><label for="parisan-contact-message">متن پیام</label><textarea id="parisan-contact-message" name="contact_message" rows="5" maxlength="3000" required></textarea></p>';
	echo '<p class="parisan-contact-privacy">با ارسال این فرم، اطلاعات تماس شما فقط برای پاسخ‌گویی به همین درخواست در اختیار مدیریت سالن قرار می‌گیرد.</p><p><button type="submit" class="button">ارسال پیام</button></p></form>';
}
add_action( 'admin_post_nopriv_parisan_submit_contact_inquiry', 'parisan_core_handle_contact_inquiry' );
add_action( 'admin_post_parisan_submit_contact_inquiry', 'parisan_core_handle_contact_inquiry' );
function parisan_core_handle_contact_inquiry() {
	$redirect = wp_get_referer();
	if ( ! $redirect || false === strpos( $redirect, '/contact' ) ) { $redirect = home_url( '/contact/' ); }
	$fail = static function( $status ) use ( $redirect ) { wp_safe_redirect( add_query_arg( 'contact_status', $status, $redirect ) ); exit; };
	if ( ! isset( $_POST['parisan_contact_nonce'] ) || ! is_string( $_POST['parisan_contact_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['parisan_contact_nonce'] ) ), 'parisan_public_contact' ) ) { $fail( 'invalid' ); }
	if ( ! empty( $_POST['website'] ) ) { $fail( 'sent' ); }
	$name = isset( $_POST['contact_name'] ) && is_string( $_POST['contact_name'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_name'] ) ) : '';
	$phone = isset( $_POST['contact_phone'] ) && is_string( $_POST['contact_phone'] ) ? trim( wp_unslash( $_POST['contact_phone'] ) ) : '';
	$phone = strtr( $phone, array( '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9' ) );
	$phone = preg_replace( '/[^+0-9]/', '', $phone );
	$email_raw = isset( $_POST['contact_email'] ) && is_string( $_POST['contact_email'] ) ? trim( wp_unslash( $_POST['contact_email'] ) ) : '';
	$email = '' === $email_raw ? '' : sanitize_email( $email_raw );
	$subject = isset( $_POST['contact_subject'] ) && is_string( $_POST['contact_subject'] ) ? sanitize_key( wp_unslash( $_POST['contact_subject'] ) ) : '';
	$message = isset( $_POST['contact_message'] ) && is_string( $_POST['contact_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['contact_message'] ) ) : '';
	if ( '' === $name || strlen( $name ) > 120 || ! preg_match( '/^\+?[0-9]{7,20}$/D', $phone ) || ( '' !== $email_raw && ( ! is_email( $email ) || '' === $email ) ) || ! in_array( $subject, array( 'service','specialist','appointment','other' ), true ) || '' === $message || strlen( $message ) > 3000 ) { $fail( 'invalid' ); }
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
	$rate_key = 'parisan_contact_' . hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
	if ( get_transient( $rate_key ) ) { $fail( 'limited' ); }
	set_transient( $rate_key, 1, 5 * MINUTE_IN_SECONDS );
	global $wpdb;
	$ok = $wpdb->insert( $wpdb->prefix . 'parisan_contact_inquiries', array( 'customer_name'=>$name, 'customer_phone'=>$phone, 'customer_email'=>$email, 'subject_key'=>$subject, 'message_body'=>$message, 'status'=>'new', 'created_at_utc'=>current_time( 'mysql', true ) ), array( '%s','%s','%s','%s','%s','%s','%s' ) );
	if ( ! $ok ) { delete_transient( $rate_key ); $fail( 'invalid' ); }
	$fail( 'sent' );
}

function parisan_core_register_contact_inbox() {
	add_submenu_page( 'edit.php?post_type=parisan_staff', __( 'پیام‌های تماس', 'parisan-core' ), __( 'پیام‌های تماس', 'parisan-core' ), 'manage_options', 'parisan-contact-inbox', 'parisan_core_render_contact_inbox' );
}
add_action( 'admin_menu', 'parisan_core_register_contact_inbox' );
function parisan_core_handle_contact_status() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'اجازه مدیریت پیام‌ها را ندارید.', 'parisan-core' ), '', array( 'response'=>403 ) ); }
	$id = isset( $_POST['inquiry_id'] ) ? absint( $_POST['inquiry_id'] ) : 0;
	check_admin_referer( 'parisan_contact_status_' . $id, 'parisan_inquiry_nonce' );
	$status = isset( $_POST['inquiry_status'] ) && is_string( $_POST['inquiry_status'] ) ? sanitize_key( wp_unslash( $_POST['inquiry_status'] ) ) : '';
	if ( ! in_array( $status, array( 'new','in_progress','closed' ), true ) ) { $status = 'new'; }
	global $wpdb;
	$wpdb->update( $wpdb->prefix . 'parisan_contact_inquiries', array( 'status'=>$status, 'updated_at_utc'=>current_time( 'mysql', true ) ), array( 'id'=>$id ), array( '%s','%s' ), array( '%d' ) );
	wp_safe_redirect( add_query_arg( array( 'post_type'=>'parisan_staff','page'=>'parisan-contact-inbox','updated'=>1 ), admin_url( 'edit.php' ) ) ); exit;
}
add_action( 'admin_post_parisan_update_contact_status', 'parisan_core_handle_contact_status' );
function parisan_core_render_contact_inbox() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'اجازه مشاهده پیام‌ها را ندارید.', 'parisan-core' ), '', array( 'response'=>403 ) ); }
	global $wpdb;
	$table = $wpdb->prefix . 'parisan_contact_inquiries';
	$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY FIELD(status,'new','in_progress','closed'), created_at_utc DESC LIMIT 300", ARRAY_A );
	$labels = array( 'new'=>'جدید','in_progress'=>'در حال پیگیری','closed'=>'بسته‌شده' );
	echo '<div class="wrap"><h1>صندوق پیام‌های تماس</h1><p>اطلاعات تماس فقط برای مدیران دارای دسترسی مدیریت در دسترس است.</p><table class="widefat striped"><thead><tr><th>تاریخ</th><th>نام</th><th>تماس</th><th>موضوع</th><th>پیام</th><th>وضعیت</th></tr></thead><tbody>';
	foreach ( (array) $rows as $row ) { $dt = ( new DateTimeImmutable( $row['created_at_utc'], new DateTimeZone( 'UTC' ) ) )->setTimezone( wp_timezone() ); echo '<tr><td>' . esc_html( wp_date( 'Y/m/d H:i', $dt->getTimestamp(), wp_timezone() ) ) . '</td><td>' . esc_html( $row['customer_name'] ) . '</td><td><a dir="ltr" href="tel:' . esc_attr( $row['customer_phone'] ) . '">' . esc_html( $row['customer_phone'] ) . '</a><br>' . esc_html( $row['customer_email'] ) . '</td><td>' . esc_html( $row['subject_key'] ) . '</td><td>' . esc_html( $row['message_body'] ) . '</td><td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="parisan_update_contact_status"><input type="hidden" name="inquiry_id" value="' . esc_attr( $row['id'] ) . '">'; wp_nonce_field( 'parisan_contact_status_' . (int) $row['id'], 'parisan_inquiry_nonce' ); echo '<select name="inquiry_status">'; foreach ( $labels as $key=>$label ) { echo '<option value="' . esc_attr( $key ) . '" ' . selected( $row['status'], $key, false ) . '>' . esc_html( $label ) . '</option>'; } echo '</select> <button class="button">ذخیره</button></form></td></tr>'; }
	if ( ! $rows ) { echo '<tr><td colspan="6">پیامی ثبت نشده است.</td></tr>'; }
	echo '</tbody></table><p>نمایش حداکثر ۳۰۰ پیام اخیر؛ حذف خودکار یا ارسال ایمیل/SMS در این نسخه انجام نمی‌شود.</p></div>';
}
