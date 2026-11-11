<?php
/**
 * Auditable resource maintenance windows and safe cancellation.
 *
 * @package ParisanCore
 */
defined( 'ABSPATH' ) || exit;

function parisan_core_register_resource_maintenance_page() {
	add_submenu_page( 'edit.php?post_type=parisan_staff', __( 'تعمیر و توقف منابع', 'parisan-core' ), __( 'تعمیر و توقف منابع', 'parisan-core' ), 'edit_others_posts', 'parisan-resource-maintenance', 'parisan_core_render_resource_maintenance_page' );
}
add_action( 'admin_menu', 'parisan_core_register_resource_maintenance_page' );

/**
 * Convert a strict salon-local datetime-local value to UTC.
 *
 * @param string $value Local datetime.
 * @return DateTimeImmutable|WP_Error
 */
function parisan_core_parse_maintenance_local_datetime( $value ) {
	if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}T(?:[01]\d|2[0-3]):[0-5]\d$/D', $value ) ) {
		return new WP_Error( 'parisan_invalid_maintenance_datetime', __( 'تاریخ و ساعت واردشده معتبر نیست.', 'parisan-core' ) );
	}
	$local = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $value, wp_timezone() );
	$errors = DateTimeImmutable::getLastErrors();
	if ( false === $local || ( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) || $local->format( 'Y-m-d\TH:i' ) !== $value ) {
		return new WP_Error( 'parisan_invalid_maintenance_datetime', __( 'تاریخ و ساعت واردشده معتبر نیست.', 'parisan-core' ) );
	}
	return $local->setTimezone( new DateTimeZone( 'UTC' ) );
}

function parisan_core_handle_resource_maintenance_create() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'اجازه ثبت توقف منبع را ندارید.', 'parisan-core' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'parisan_resource_maintenance_create', 'parisan_resource_maintenance_nonce' );
	$resource_id = isset( $_POST['resource_id'] ) && is_scalar( $_POST['resource_id'] ) ? absint( wp_unslash( $_POST['resource_id'] ) ) : 0;
	$start_raw = isset( $_POST['starts_at_local'] ) && is_string( $_POST['starts_at_local'] ) ? sanitize_text_field( wp_unslash( $_POST['starts_at_local'] ) ) : '';
	$end_raw = isset( $_POST['ends_at_local'] ) && is_string( $_POST['ends_at_local'] ) ? sanitize_text_field( wp_unslash( $_POST['ends_at_local'] ) ) : '';
	$reason = isset( $_POST['reason'] ) && is_string( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';
	$start = parisan_core_parse_maintenance_local_datetime( $start_raw );
	$end = parisan_core_parse_maintenance_local_datetime( $end_raw );
	$reason_length = function_exists( 'mb_strlen' ) ? mb_strlen( $reason, 'UTF-8' ) : strlen( $reason );
	if ( ! $resource_id || 'parisan_resource' !== get_post_type( $resource_id ) || 'publish' !== get_post_status( $resource_id ) || ! current_user_can( 'edit_post', $resource_id ) || is_wp_error( $start ) || is_wp_error( $end ) || $end <= $start || $start <= new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) || $end > $start->modify( '+90 days' ) || '' === trim( $reason ) || $reason_length > 500 ) {
		wp_die( esc_html__( 'منبع، بازه زمانی یا دلیل توقف معتبر نیست. توقف باید از آینده آغاز شود و حداکثر ۹۰ روز طول بکشد.', 'parisan-core' ), '', array( 'response' => 400 ) );
	}
	$locks = parisan_core_acquire_resource_schedule_locks( array( $resource_id ) );
	if ( is_wp_error( $locks ) ) {
		wp_die( esc_html( $locks->get_error_message() ), '', array( 'response' => 503 ) );
	}
	global $wpdb;
	$bookings = $wpdb->prefix . 'parisan_bookings';
	$relations = $wpdb->prefix . 'parisan_booking_resources';
	$conflicts = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(DISTINCT b.id) FROM {$bookings} b
		LEFT JOIN {$relations} br ON br.booking_id = b.id
		WHERE b.status IN ('pending','confirmed')
		AND (b.resource_id = %d OR br.resource_id = %d)
		AND b.starts_at_utc < %s AND b.ends_at_utc > %s",
		$resource_id,
		$resource_id,
		$end->format( 'Y-m-d H:i:s' ),
		$start->format( 'Y-m-d H:i:s' )
	) );
	$table = $wpdb->prefix . 'parisan_resource_maintenance';
	$overlaps = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$table} WHERE resource_id = %d AND status = 'active'
		AND starts_at_utc < %s AND ends_at_utc > %s",
		$resource_id,
		$end->format( 'Y-m-d H:i:s' ),
		$start->format( 'Y-m-d H:i:s' )
	) );
	$inserted = false;
	if ( ! $conflicts && ! $overlaps ) {
		$inserted = $wpdb->insert( $table, array(
			'resource_id' => $resource_id,
			'starts_at_utc' => $start->format( 'Y-m-d H:i:s' ),
			'ends_at_utc' => $end->format( 'Y-m-d H:i:s' ),
			'reason' => $reason,
			'status' => 'active',
			'created_by' => get_current_user_id(),
			'created_at_utc' => current_time( 'mysql', true ),
		), array( '%d', '%s', '%s', '%s', '%s', '%d', '%s' ) );
	}
	parisan_core_release_resource_schedule_locks( $locks );
	if ( $conflicts || $overlaps || false === $inserted ) {
		$code = $conflicts ? 'parisan_maintenance_booking_conflict' : ( $overlaps ? 'parisan_maintenance_overlap' : 'parisan_maintenance_write_failed' );
		$message = $conflicts ? __( 'در این بازه برای منبع رزرو فعال وجود دارد؛ توقف ثبت نشد.', 'parisan-core' ) : ( $overlaps ? __( 'با یک توقف فعال دیگر هم‌پوشانی دارد.', 'parisan-core' ) : __( 'ثبت توقف منبع انجام نشد.', 'parisan-core' ) );
		wp_die( esc_html( $message . ' (' . $code . ')' ), '', array( 'response' => 409 ) );
	}
	wp_safe_redirect( add_query_arg( array( 'post_type' => 'parisan_staff', 'page' => 'parisan-resource-maintenance', 'saved' => 1 ), admin_url( 'edit.php' ) ) );
	exit;
}
add_action( 'admin_post_parisan_resource_maintenance_create', 'parisan_core_handle_resource_maintenance_create' );

function parisan_core_handle_resource_maintenance_cancel() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'اجازه لغو توقف منبع را ندارید.', 'parisan-core' ), '', array( 'response' => 403 ) );
	}
	$id = isset( $_POST['maintenance_id'] ) && is_scalar( $_POST['maintenance_id'] ) ? absint( wp_unslash( $_POST['maintenance_id'] ) ) : 0;
	check_admin_referer( 'parisan_resource_maintenance_cancel_' . $id, 'parisan_resource_maintenance_cancel_nonce' );
	global $wpdb;
	$table = $wpdb->prefix . 'parisan_resource_maintenance';
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT id,resource_id FROM {$table} WHERE id=%d AND status='active'", $id ), ARRAY_A );
	if ( ! $row || ! current_user_can( 'edit_post', (int) $row['resource_id'] ) ) {
		wp_die( esc_html__( 'توقف منبع پیدا نشد یا اجازه لغو آن را ندارید.', 'parisan-core' ), '', array( 'response' => 404 ) );
	}
	$locks = parisan_core_acquire_resource_schedule_locks( array( (int) $row['resource_id'] ) );
	if ( is_wp_error( $locks ) ) {
		wp_die( esc_html( $locks->get_error_message() ), '', array( 'response' => 503 ) );
	}
	$updated = $wpdb->update( $table, array( 'status' => 'cancelled', 'cancelled_by' => get_current_user_id(), 'cancelled_at_utc' => current_time( 'mysql', true ) ), array( 'id' => $id, 'status' => 'active' ), array( '%s', '%d', '%s' ), array( '%d', '%s' ) );
	parisan_core_release_resource_schedule_locks( $locks );
	if ( false === $updated || 0 === $updated ) {
		wp_die( esc_html__( 'لغو توقف منبع انجام نشد یا قبلاً لغو شده است.', 'parisan-core' ), '', array( 'response' => 409 ) );
	}
	wp_safe_redirect( add_query_arg( array( 'post_type' => 'parisan_staff', 'page' => 'parisan-resource-maintenance', 'cancelled' => 1 ), admin_url( 'edit.php' ) ) );
	exit;
}
add_action( 'admin_post_parisan_resource_maintenance_cancel', 'parisan_core_handle_resource_maintenance_cancel' );

function parisan_core_render_resource_maintenance_page() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'اجازه مشاهده توقف منابع را ندارید.', 'parisan-core' ), '', array( 'response' => 403 ) );
	}
	$resources = get_posts( array( 'post_type' => 'parisan_resource', 'post_status' => 'publish', 'posts_per_page' => 300, 'orderby' => 'title', 'order' => 'ASC' ) );
	$resources = array_values( array_filter( $resources, static function ( $resource ) { return current_user_can( 'edit_post', $resource->ID ); } ) );
	$ids = array_map( static function ( $resource ) { return (int) $resource->ID; }, $resources );
	echo '<div class="wrap"><h1>تعمیر و توقف منابع سالن</h1>';
	if ( isset( $_GET['saved'] ) ) { echo '<div class="notice notice-success"><p>بازه توقف ثبت شد و در تخصیص نوبت‌های جدید لحاظ می‌شود.</p></div>'; }
	if ( isset( $_GET['cancelled'] ) ) { echo '<div class="notice notice-success"><p>توقف لغو شد؛ منبع دوباره می‌تواند در تخصیص نوبت استفاده شود.</p></div>'; }
	echo '<p>توقف منابع فقط رزروهای جدید را مسدود می‌کند. بازه‌ای که با رزرو در انتظار یا تأییدشده تداخل داشته باشد پذیرفته نمی‌شود. همه زمان‌ها بر اساس منطقه زمانی سالن وارد و در دیتابیس به UTC ذخیره می‌شوند.</p>';
	if ( $resources ) {
		echo '<h2>ثبت بازه تعمیر یا عدم دسترسی</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="parisan_resource_maintenance_create">';
		wp_nonce_field( 'parisan_resource_maintenance_create', 'parisan_resource_maintenance_nonce' );
		echo '<p><label>منبع <select name="resource_id" required>';
		foreach ( $resources as $resource ) { echo '<option value="' . esc_attr( $resource->ID ) . '">' . esc_html( get_the_title( $resource ) ) . '</option>'; }
		echo '</select></label></p><p><label>شروع <input type="datetime-local" name="starts_at_local" required></label> <label>پایان <input type="datetime-local" name="ends_at_local" required></label></p><p><label>علت توقف <textarea name="reason" maxlength="500" required></textarea></label></p><button class="button button-primary">ثبت توقف</button></form>';
	} else { echo '<p>منبع قابل‌ویرایش برای این حساب پیدا نشد.</p>'; }
	if ( $ids ) {
		global $wpdb;
		$table = $wpdb->prefix . 'parisan_resource_maintenance';
		$ph = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE resource_id IN ({$ph}) ORDER BY starts_at_utc DESC LIMIT 200", $ids ), ARRAY_A );
		echo '<h2>تاریخچه توقف‌ها</h2><table class="widefat striped"><thead><tr><th>منبع</th><th>شروع (UTC)</th><th>پایان (UTC)</th><th>علت</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>';
		foreach ( (array) $rows as $row ) {
			$local_start = ( new DateTimeImmutable( $row['starts_at_utc'], new DateTimeZone( 'UTC' ) ) )->setTimezone( wp_timezone() );
			$local_end = ( new DateTimeImmutable( $row['ends_at_utc'], new DateTimeZone( 'UTC' ) ) )->setTimezone( wp_timezone() );
			echo '<tr><td>' . esc_html( get_the_title( (int) $row['resource_id'] ) ) . '</td><td>' . esc_html( $local_start->format( 'Y/m/d H:i' ) ) . '</td><td>' . esc_html( $local_end->format( 'Y/m/d H:i' ) ) . '</td><td>' . esc_html( $row['reason'] ) . '</td><td>' . esc_html( 'active' === $row['status'] ? 'فعال' : 'لغوشده' ) . '</td><td>';
			if ( 'active' === $row['status'] ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="parisan_resource_maintenance_cancel"><input type="hidden" name="maintenance_id" value="' . esc_attr( $row['id'] ) . '">';
				wp_nonce_field( 'parisan_resource_maintenance_cancel_' . $row['id'], 'parisan_resource_maintenance_cancel_nonce' );
				echo '<button class="button">لغو توقف</button></form>';
			} else { echo '—'; }
			echo '</td></tr>';
		}
		if ( ! $rows ) { echo '<tr><td colspan="6">هنوز توقفی ثبت نشده است.</td></tr>'; }
		echo '</tbody></table>';
	}
	echo '</div>';
}
