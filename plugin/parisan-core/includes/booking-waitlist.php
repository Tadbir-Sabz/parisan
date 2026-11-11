<?php
/**
 * Customer waitlist capture and permission-scoped salon queue.
 *
 * @package ParisanCore
 */
defined( 'ABSPATH' ) || exit;

function parisan_core_register_waitlist_admin_page() {
	add_submenu_page( 'edit.php?post_type=parisan_staff', __( 'فهرست انتظار', 'parisan-core' ), __( 'فهرست انتظار', 'parisan-core' ), 'edit_others_posts', 'parisan-waitlist', 'parisan_core_render_waitlist_admin_page' );
}
add_action( 'admin_menu', 'parisan_core_register_waitlist_admin_page' );

function parisan_core_waitlist_shortcode() {
	$services = get_posts( array( 'post_type' => 'parisan_service', 'post_status' => 'publish', 'numberposts' => 200, 'orderby' => 'title', 'order' => 'ASC' ) );
	if ( ! $services ) {
		return '<section class="parisan-waitlist" dir="rtl"><p>در حال حاضر خدمتی برای ثبت درخواست فهرست انتظار وجود ندارد.</p></section>';
	}
	ob_start();
	?>
	<section class="parisan-waitlist" dir="rtl">
		<h2><?php esc_html_e( 'درخواست فهرست انتظار', 'parisan-core' ); ?></h2>
		<p><?php esc_html_e( 'اگر زمان دلخواه شما آزاد نیست، درخواست خود را ثبت کنید تا سالن در صورت ایجاد ظرفیت با شما تماس بگیرد. ثبت درخواست به معنی رزرو قطعی یا تضمین زمان نیست.', 'parisan-core' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="parisan_waitlist_submit">
			<?php wp_nonce_field( 'parisan_waitlist_submit', 'parisan_waitlist_nonce' ); ?>
			<p><label for="parisan-waitlist-service"><?php esc_html_e( 'خدمت', 'parisan-core' ); ?></label>
				<select id="parisan-waitlist-service" name="service_id" required><option value=""><?php esc_html_e( 'انتخاب خدمت', 'parisan-core' ); ?></option><?php foreach ( $services as $service ) : ?><option value="<?php echo esc_attr( $service->ID ); ?>"><?php echo esc_html( get_the_title( $service ) ); ?></option><?php endforeach; ?></select></p>
			<p><label for="parisan-waitlist-staff"><?php esc_html_e( 'متخصص دلخواه (اختیاری)', 'parisan-core' ); ?></label>
				<select id="parisan-waitlist-staff" name="staff_id"><option value="0"><?php esc_html_e( 'هر متخصص واجد شرایط', 'parisan-core' ); ?></option><?php
				$staff = get_posts( array( 'post_type' => 'parisan_staff', 'post_status' => 'publish', 'numberposts' => 200 ) );
				foreach ( $staff as $member ) : ?><option value="<?php echo esc_attr( $member->ID ); ?>"><?php echo esc_html( get_the_title( $member ) ); ?></option><?php endforeach; ?></select></p>
			<p><label for="parisan-waitlist-date"><?php esc_html_e( 'تاریخ موردنظر', 'parisan-core' ); ?></label><input id="parisan-waitlist-date" type="date" name="preferred_date" min="<?php echo esc_attr( current_datetime()->format( 'Y-m-d' ) ); ?>" max="<?php echo esc_attr( current_datetime()->modify( '+' . parisan_core_get_booking_horizon_days() . ' days' )->format( 'Y-m-d' ) ); ?>" required></p>
			<p><label for="parisan-waitlist-period"><?php esc_html_e( 'بازه زمانی', 'parisan-core' ); ?></label><select id="parisan-waitlist-period" name="preferred_period"><option value="any">فرقی ندارد</option><option value="morning">صبح</option><option value="afternoon">ظهر و بعدازظهر</option><option value="evening">عصر</option></select></p>
			<p><label for="parisan-waitlist-name"><?php esc_html_e( 'نام و نام خانوادگی', 'parisan-core' ); ?></label><input id="parisan-waitlist-name" name="customer_name" maxlength="190" autocomplete="name" required></p>
			<p><label for="parisan-waitlist-phone"><?php esc_html_e( 'شماره تماس', 'parisan-core' ); ?></label><input id="parisan-waitlist-phone" name="customer_phone" type="tel" maxlength="24" autocomplete="tel" required></p>
			<p><label for="parisan-waitlist-email"><?php esc_html_e( 'ایمیل (اختیاری)', 'parisan-core' ); ?></label><input id="parisan-waitlist-email" name="customer_email" type="email" maxlength="190" autocomplete="email"></p>
			<p class="parisan-waitlist-trap" aria-hidden="true"><label for="parisan-waitlist-website">Website</label><input id="parisan-waitlist-website" name="website" tabindex="-1" autocomplete="off"></p>
			<p><label><input type="checkbox" name="contact_consent" value="1" required> <?php esc_html_e( 'رضایت می‌دهم سالن برای پیگیری همین درخواست با من تماس بگیرد و اطلاعاتم را تا تعیین تکلیف درخواست نگهداری کند.', 'parisan-core' ); ?></label></p>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'ثبت درخواست', 'parisan-core' ); ?></button>
		</form>
		<?php if ( isset( $_GET['waitlist_submitted'] ) ) : ?><p role="status"><?php esc_html_e( 'درخواست شما ثبت شد. این درخواست رزرو قطعی نیست و سالن در صورت امکان با شما تماس می‌گیرد.', 'parisan-core' ); ?></p><?php endif; ?>
	</section>
	<style>.parisan-waitlist{max-width:680px;margin-inline:auto;padding:24px;border:1px solid #e2e4e7;border-radius:12px}.parisan-waitlist form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.parisan-waitlist form p{margin:0;display:grid;gap:6px}.parisan-waitlist form p:nth-last-child(-n+3){grid-column:1/-1}.parisan-waitlist input:not([type=checkbox]),.parisan-waitlist select{width:100%;min-height:42px}.parisan-waitlist-trap{position:absolute!important;left:-10000px!important;width:1px!important;height:1px!important;overflow:hidden!important}@media(max-width:600px){.parisan-waitlist form{grid-template-columns:1fr}}</style>
	<?php
	return ob_get_clean();
}
add_shortcode( 'parisan_waitlist', 'parisan_core_waitlist_shortcode' );

function parisan_core_handle_waitlist_submit() {
	if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		wp_die( esc_html__( 'درخواست معتبر نیست.', 'parisan-core' ), '', array( 'response' => 405 ) );
	}
	check_admin_referer( 'parisan_waitlist_submit', 'parisan_waitlist_nonce' );
	if ( ! empty( $_POST['website'] ) ) {
		wp_die( esc_html__( 'درخواست ثبت نشد.', 'parisan-core' ), '', array( 'response' => 400 ) );
	}
	$referer = wp_get_raw_referer();
	if ( ! $referer || wp_parse_url( $referer, PHP_URL_HOST ) !== wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) {
		wp_die( esc_html__( 'مبدأ درخواست معتبر نیست.', 'parisan-core' ), '', array( 'response' => 403 ) );
	}
	$limited = parisan_core_check_rate_limit( 'public_waitlist', 3, 900 );
	if ( true !== $limited ) {
		wp_die( esc_html__( 'تعداد درخواست‌ها بیش از حد مجاز است؛ کمی بعد دوباره تلاش کنید.', 'parisan-core' ), '', array( 'response' => 429 ) );
	}
	$service_id = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;
	$staff_id = isset( $_POST['staff_id'] ) ? absint( $_POST['staff_id'] ) : 0;
	$date = isset( $_POST['preferred_date'] ) && is_string( $_POST['preferred_date'] ) ? sanitize_text_field( wp_unslash( $_POST['preferred_date'] ) ) : '';
	$period = isset( $_POST['preferred_period'] ) && is_string( $_POST['preferred_period'] ) ? sanitize_key( wp_unslash( $_POST['preferred_period'] ) ) : 'any';
	$name = isset( $_POST['customer_name'] ) && is_string( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '';
	$phone = isset( $_POST['customer_phone'] ) && is_string( $_POST['customer_phone'] ) ? trim( wp_unslash( $_POST['customer_phone'] ) ) : '';
	$email_raw = isset( $_POST['customer_email'] ) && is_string( $_POST['customer_email'] ) ? trim( wp_unslash( $_POST['customer_email'] ) ) : '';
	$phone = strtr( $phone, array( '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9' ) );
	$phone = preg_replace( '/[^+0-9]/', '', $phone );
	$email = '' !== $email_raw ? sanitize_email( $email_raw ) : '';
	$periods = array( 'any', 'morning', 'afternoon', 'evening' );
	$timezone = wp_timezone();
	$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, $timezone );
	$errors = DateTimeImmutable::getLastErrors();
	if ( 'publish' !== get_post_status( $service_id ) || 'parisan_service' !== get_post_type( $service_id ) || ( $staff_id && ( 'publish' !== get_post_status( $staff_id ) || 'parisan_staff' !== get_post_type( $staff_id ) ) ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $date ) || false === $parsed || ( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) || $parsed->format( 'Y-m-d' ) !== $date || ! parisan_core_is_booking_date_within_horizon( $date ) || '' === $name || strlen( $name ) > 190 || ! preg_match( '/^\+?[0-9]{7,20}$/D', $phone ) || ( '' !== $email_raw && ( '' === $email || ! is_email( $email ) ) ) || ! in_array( $period, $periods, true ) || empty( $_POST['contact_consent'] ) ) {
		wp_die( esc_html__( 'اطلاعات درخواست معتبر نیست یا رضایت تماس ثبت نشده است.', 'parisan-core' ), '', array( 'response' => 400 ) );
	}
	if ( $staff_id ) {
		$eligible = get_post_meta( $staff_id, '_parisan_staff_services', true );
		$eligible = is_array( $eligible ) ? array_map( 'absint', $eligible ) : array();
		if ( ! in_array( $service_id, $eligible, true ) ) {
			wp_die( esc_html__( 'این متخصص خدمت انتخاب‌شده را ارائه نمی‌کند.', 'parisan-core' ), '', array( 'response' => 400 ) );
		}
	}
	global $wpdb;
	$table = $wpdb->prefix . 'parisan_booking_waitlist';
	$inserted = $wpdb->insert( $table, array(
		'service_id' => $service_id,
		'preferred_staff_id' => $staff_id,
		'preferred_date' => $date,
		'preferred_period' => $period,
		'customer_name' => $name,
		'customer_phone' => $phone,
		'customer_email' => $email,
		'contact_consent_at_utc' => current_time( 'mysql', true ),
		'status' => 'waiting',
		'created_at_utc' => current_time( 'mysql', true ),
		'updated_at_utc' => current_time( 'mysql', true ),
	), array( '%d','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s' ) );
	if ( false === $inserted ) {
		wp_die( esc_html__( 'ثبت درخواست انجام نشد؛ لطفاً کمی بعد تلاش کنید.', 'parisan-core' ), '', array( 'response' => 503 ) );
	}
	$target = wp_get_referer() ? wp_get_referer() : home_url( '/' );
	wp_safe_redirect( add_query_arg( 'waitlist_submitted', '1', remove_query_arg( 'waitlist_submitted', $target ) ) );
	exit;
}
add_action( 'admin_post_nopriv_parisan_waitlist_submit', 'parisan_core_handle_waitlist_submit' );
add_action( 'admin_post_parisan_waitlist_submit', 'parisan_core_handle_waitlist_submit' );

function parisan_core_handle_waitlist_status() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'اجازه تغییر فهرست انتظار را ندارید.', 'parisan-core' ), '', array( 'response' => 403 ) );
	}
	$id = isset( $_POST['waitlist_id'] ) ? absint( $_POST['waitlist_id'] ) : 0;
	check_admin_referer( 'parisan_waitlist_status_' . $id );
	$status = isset( $_POST['status'] ) && is_string( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
	if ( ! $id || ! in_array( $status, array( 'contacted', 'booked', 'closed' ), true ) ) {
		wp_die( esc_html__( 'وضعیت انتخاب‌شده معتبر نیست.', 'parisan-core' ), '', array( 'response' => 400 ) );
	}
	global $wpdb;
	$table = $wpdb->prefix . 'parisan_booking_waitlist';
	$updated = $wpdb->update( $table, array( 'status' => $status, 'handled_by' => get_current_user_id(), 'handled_at_utc' => current_time( 'mysql', true ), 'updated_at_utc' => current_time( 'mysql', true ) ), array( 'id' => $id, 'status' => 'waiting' ), array( '%s','%d','%s','%s' ), array( '%d','%s' ) );
	if ( false === $updated ) {
		wp_die( esc_html__( 'به‌روزرسانی وضعیت انجام نشد.', 'parisan-core' ), '', array( 'response' => 503 ) );
	}
	wp_safe_redirect( add_query_arg( array( 'post_type' => 'parisan_staff', 'page' => 'parisan-waitlist', 'updated' => 1 ), admin_url( 'edit.php' ) ) );
	exit;
}
add_action( 'admin_post_parisan_waitlist_status', 'parisan_core_handle_waitlist_status' );

function parisan_core_render_waitlist_admin_page() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'اجازه مشاهده فهرست انتظار را ندارید.', 'parisan-core' ), '', array( 'response' => 403 ) );
	}
	global $wpdb;
	$table = $wpdb->prefix . 'parisan_booking_waitlist';
	$filter = isset( $_GET['status'] ) && is_string( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'waiting';
	$allowed = array( 'waiting', 'contacted', 'booked', 'closed', 'all' );
	if ( ! in_array( $filter, $allowed, true ) ) { $filter = 'waiting'; }
	$sql = "SELECT * FROM {$table}";
	$args = array();
	if ( 'all' !== $filter ) { $sql .= ' WHERE status = %s'; $args[] = $filter; }
	$sql .= ' ORDER BY created_at_utc DESC LIMIT 300';
	$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
	$labels = array( 'waiting'=>'در انتظار پیگیری', 'contacted'=>'تماس گرفته شد', 'booked'=>'به رزرو تبدیل شد', 'closed'=>'بسته‌شده' );
	echo '<div class="wrap" dir="rtl"><h1>فهرست انتظار مشتریان</h1><p>درخواست‌های این فهرست رزرو قطعی نیستند. تماس با مشتری فقط با رضایت ثبت‌شده انجام می‌شود؛ ارسال خودکار پیامک یا ایمیل در این بخش فعال نیست.</p>';
	echo '<form method="get"><input type="hidden" name="post_type" value="parisan_staff"><input type="hidden" name="page" value="parisan-waitlist"><select name="status">';
	foreach ( $allowed as $status ) { echo '<option value="' . esc_attr( $status ) . '"' . selected( $filter, $status, false ) . '>' . esc_html( 'all' === $status ? 'همه' : $labels[ $status ] ) . '</option>'; }
	echo '</select> <button class="button">فیلتر</button></form>';
	echo '<table class="widefat striped"><thead><tr><th>مشتری</th><th>تماس</th><th>خدمت / متخصص</th><th>تاریخ و بازه</th><th>ثبت درخواست</th><th>وضعیت و اقدام</th></tr></thead><tbody>';
	foreach ( (array) $rows as $row ) {
		$staff_label = $row['preferred_staff_id'] ? get_the_title( (int) $row['preferred_staff_id'] ) : 'هر متخصص واجد شرایط';
		$period_labels = array( 'any'=>'هر زمان', 'morning'=>'صبح', 'afternoon'=>'ظهر و بعدازظهر', 'evening'=>'عصر' );
		echo '<tr><td>' . esc_html( $row['customer_name'] ) . '</td><td dir="ltr">' . esc_html( $row['customer_phone'] ) . '<br>' . esc_html( $row['customer_email'] ) . '</td><td>' . esc_html( get_the_title( (int) $row['service_id'] ) ) . '<br>' . esc_html( $staff_label ) . '</td><td>' . esc_html( $row['preferred_date'] . ' · ' . ( $period_labels[ $row['preferred_period'] ] ?? '—' ) ) . '</td><td>' . esc_html( $row['created_at_utc'] ) . '</td><td><strong>' . esc_html( $labels[ $row['status'] ] ?? $row['status'] ) . '</strong>';
		if ( 'waiting' === $row['status'] ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="parisan_waitlist_status"><input type="hidden" name="waitlist_id" value="' . esc_attr( $row['id'] ) . '">';
			wp_nonce_field( 'parisan_waitlist_status_' . (int) $row['id'] );
			echo '<select name="status"><option value="contacted">تماس گرفته شد</option><option value="booked">به رزرو تبدیل شد</option><option value="closed">بستن درخواست</option></select> <button class="button button-small">ثبت</button></form>';
		}
		echo '</td></tr>';
	}
	if ( ! $rows ) { echo '<tr><td colspan="6">درخواستی در این بخش وجود ندارد.</td></tr>'; }
	echo '</tbody></table><p>برای حفظ حریم خصوصی، فقط مدیران دارای دسترسی مدیریت نوبت‌ها می‌توانند اطلاعات تماس را مشاهده کنند. درخواست‌ها پس از تعیین تکلیف به‌صورت تاریخچه نگهداری می‌شوند.</p></div>';
}

function parisan_core_waitlist_privacy_exporter( $exporters ) {
	$exporters['parisan-waitlist'] = array( 'exporter_friendly_name' => __( 'درخواست‌های فهرست انتظار پاریسان', 'parisan-core' ), 'callback' => 'parisan_core_waitlist_privacy_exporter_callback' );
	return $exporters;
}
add_filter( 'wp_privacy_personal_data_exporters', 'parisan_core_waitlist_privacy_exporter' );

function parisan_core_waitlist_privacy_exporter_callback( $email, $page = 1 ) {
	global $wpdb;
	if ( ! is_email( $email ) ) { return array( 'data' => array(), 'done' => true ); }
	$table = $wpdb->prefix . 'parisan_booking_waitlist';
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,service_id,preferred_staff_id,preferred_date,preferred_period,customer_name,customer_phone,customer_email,status,created_at_utc FROM {$table} WHERE customer_email=%s ORDER BY id ASC LIMIT 50 OFFSET %d", $email, max( 0, ( absint( $page ) - 1 ) * 50 ) ), ARRAY_A );
	$data = array();
	foreach ( (array) $rows as $row ) {
		$fields = array();
		foreach ( $row as $key => $value ) { $fields[] = array( 'name' => sanitize_text_field( $key ), 'value' => (string) $value ); }
		$data[] = array( 'group_id' => 'parisan-waitlist', 'group_label' => __( 'درخواست‌های فهرست انتظار', 'parisan-core' ), 'item_id' => 'parisan-waitlist-' . (int) $row['id'], 'data' => $fields );
	}
	return array( 'data' => $data, 'done' => count( $data ) < 50 );
}
