<?php
/**
 * Explicit-consent appointment email reminders and delivery audit queue.
 *
 * @package ParisanCore
 */
defined( 'ABSPATH' ) || exit;

function parisan_core_reminder_cron_schedules( $schedules ) {
	$schedules['parisan_fifteen_minutes'] = array( 'interval' => 15 * MINUTE_IN_SECONDS, 'display' => __( 'هر پانزده دقیقه', 'parisan-core' ) );
	return $schedules;
}
add_filter( 'cron_schedules', 'parisan_core_reminder_cron_schedules' );
add_action( 'parisan_core_process_booking_reminders', 'parisan_core_process_booking_reminders' );
add_action( 'init', 'parisan_core_ensure_reminder_cron' );

function parisan_core_ensure_reminder_cron() {
	if ( ! wp_next_scheduled( 'parisan_core_process_booking_reminders' ) ) {
		wp_schedule_event( time() + 300, 'parisan_fifteen_minutes', 'parisan_core_process_booking_reminders' );
	}
}

function parisan_core_sync_booking_reminders( $booking_id ) {
	global $wpdb;
	$booking_id = absint( $booking_id );
	if ( ! $booking_id ) { return new WP_Error( 'parisan_reminder_invalid_booking', __( 'رزرو معتبر نیست.', 'parisan-core' ) ); }
	$bookings = $wpdb->prefix . 'parisan_bookings';
	$queue = $wpdb->prefix . 'parisan_booking_reminders';
	$booking = $wpdb->get_row( $wpdb->prepare( "SELECT id,customer_name,customer_email,customer_email_consent,service_id,staff_id,starts_at_utc,ends_at_utc,salon_timezone,status FROM {$bookings} WHERE id=%d", $booking_id ), ARRAY_A );
	if ( ! is_array( $booking ) ) { return new WP_Error( 'parisan_reminder_booking_missing', __( 'رزرو برای زمان‌بندی یادآور پیدا نشد.', 'parisan-core' ) ); }
	$now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
	$active = in_array( $booking['status'], array( 'pending', 'confirmed' ), true );
	$consented = 1 === (int) $booking['customer_email_consent'] && is_email( $booking['customer_email'] );
	if ( ! $active || ! $consented ) {
		$wpdb->query( $wpdb->prepare( "UPDATE {$queue} SET status='skipped',last_error=%s,updated_at_utc=%s WHERE booking_id=%d AND status IN ('pending','failed')", ! $active ? 'booking_not_active' : 'email_consent_missing', current_time( 'mysql', true ), $booking_id ) );
		return true;
	}
	try {
		$start = new DateTimeImmutable( $booking['starts_at_utc'], new DateTimeZone( 'UTC' ) );
	} catch ( Exception $exception ) {
		return new WP_Error( 'parisan_reminder_invalid_time', __( 'زمان رزرو برای یادآوری معتبر نیست.', 'parisan-core' ) );
	}
	foreach ( array( '24h' => 24, '2h' => 2 ) as $kind => $hours ) {
		$scheduled = $start->modify( '-' . $hours . ' hours' );
		$future = $scheduled > $now;
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id,status,attempts,last_error FROM {$queue} WHERE booking_id=%d AND reminder_kind=%s", $booking_id, $kind ), ARRAY_A );
		if ( is_array( $existing ) ) {
			if ( ( in_array( $existing['status'], array( 'pending', 'failed' ), true ) && (int) $existing['attempts'] < 3 ) || ( 'skipped' === $existing['status'] && 'reminder_time_passed' === $existing['last_error'] ) ) {
				$wpdb->update( $queue, array( 'status' => $future ? 'pending' : 'skipped', 'scheduled_at_utc' => $scheduled->format( 'Y-m-d H:i:s' ), 'last_error' => $future ? '' : 'reminder_time_passed', 'updated_at_utc' => current_time( 'mysql', true ) ), array( 'id' => (int) $existing['id'] ), array( '%s','%s','%s','%s' ), array( '%d' ) );
			}
			continue;
		}
		$inserted = $wpdb->insert( $queue, array(
			'booking_id' => $booking_id,
			'reminder_kind' => $kind,
			'status' => $future ? 'pending' : 'skipped',
			'scheduled_at_utc' => $scheduled->format( 'Y-m-d H:i:s' ),
			'last_error' => $future ? '' : 'reminder_time_passed',
			'created_at_utc' => current_time( 'mysql', true ),
			'updated_at_utc' => current_time( 'mysql', true ),
		), array( '%d','%s','%s','%s','%s','%s','%s' ) );
		if ( false === $inserted ) {
			return new WP_Error( 'parisan_reminder_queue_failed', __( 'ثبت یادآور رزرو انجام نشد.', 'parisan-core' ) );
		}
	}
	return true;
}

function parisan_core_skip_booking_reminders( $booking_id, $reason = 'booking_not_active' ) {
	global $wpdb;
	$queue = $wpdb->prefix . 'parisan_booking_reminders';
	$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$queue} SET status='skipped',last_error=%s,updated_at_utc=%s WHERE booking_id=%d AND status IN ('pending','failed')", sanitize_key( $reason ), current_time( 'mysql', true ), absint( $booking_id ) ) );
	return false === $updated ? new WP_Error( 'parisan_reminder_skip_failed', __( 'به‌روزرسانی یادآورهای رزرو انجام نشد.', 'parisan-core' ) ) : true;
}

function parisan_core_process_booking_reminders() {
	global $wpdb;
	$queue = $wpdb->prefix . 'parisan_booking_reminders';
	$bookings = $wpdb->prefix . 'parisan_bookings';
	$now = current_time( 'mysql', true );
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT r.id,r.booking_id,r.reminder_kind,r.attempts,b.customer_name,b.customer_email,b.customer_email_consent,b.service_id,b.staff_id,b.starts_at_utc,b.salon_timezone,b.status AS booking_status
		FROM {$queue} r INNER JOIN {$bookings} b ON b.id=r.booking_id
		WHERE r.status IN ('pending','failed') AND r.scheduled_at_utc <= %s AND r.attempts < 3
		ORDER BY r.scheduled_at_utc ASC LIMIT 25",
		$now
	), ARRAY_A );
	if ( null === $rows || '' !== $wpdb->last_error ) { return; }
	foreach ( $rows as $row ) {
		$claimed = $wpdb->query( $wpdb->prepare( "UPDATE {$queue} SET status='sending',attempts=attempts+1,updated_at_utc=%s WHERE id=%d AND status IN ('pending','failed') AND scheduled_at_utc<=%s", $now, (int) $row['id'], $now ) );
		if ( 1 !== (int) $claimed ) { continue; }
		if ( ! in_array( $row['booking_status'], array( 'pending', 'confirmed' ), true ) || 1 !== (int) $row['customer_email_consent'] || ! is_email( $row['customer_email'] ) ) {
			$wpdb->update( $queue, array( 'status' => 'skipped', 'last_error' => 'booking_or_consent_unavailable', 'updated_at_utc' => current_time( 'mysql', true ) ), array( 'id' => (int) $row['id'] ), array( '%s','%s','%s' ), array( '%d' ) );
			continue;
		}
		try {
			$local = ( new DateTimeImmutable( $row['starts_at_utc'], new DateTimeZone( 'UTC' ) ) )->setTimezone( new DateTimeZone( $row['salon_timezone'] ) );
		} catch ( Exception $exception ) {
			$local = ( new DateTimeImmutable( $row['starts_at_utc'], new DateTimeZone( 'UTC' ) ) )->setTimezone( wp_timezone() );
		}
		$kind_label = '24h' === $row['reminder_kind'] ? 'یادآوری یک روز مانده به نوبت' : 'یادآوری دو ساعت مانده به نوبت';
		$subject = sprintf( __( 'یادآوری نوبت شما در %s', 'parisan-core' ), get_bloginfo( 'name' ) );
		$message = sprintf(
			"سلام %s،\n\nاین پیام یادآوری نوبت شماست.\nخدمت: %s\nمتخصص: %s\nزمان: %s\n\nبرای مشاهده یا تغییر رزرو از صفحه رزرو سالن و توکن محرمانه خود استفاده کنید.\n\n%s",
			$row['customer_name'],
			get_the_title( (int) $row['service_id'] ),
			get_the_title( (int) $row['staff_id'] ),
			$local->format( 'Y-m-d H:i' ),
			$kind_label
		);
		$sent = wp_mail( $row['customer_email'], $subject, $message, array( 'Content-Type: text/plain; charset=UTF-8' ) );
		$attempts = (int) $row['attempts'] + 1;
		if ( $sent ) {
			$wpdb->update( $queue, array( 'status' => 'sent', 'sent_at_utc' => current_time( 'mysql', true ), 'last_error' => '', 'updated_at_utc' => current_time( 'mysql', true ) ), array( 'id' => (int) $row['id'] ), array( '%s','%s','%s','%s' ), array( '%d' ) );
		} else {
			$wpdb->update( $queue, array( 'status' => 'failed', 'scheduled_at_utc' => gmdate( 'Y-m-d H:i:s', time() + 30 * MINUTE_IN_SECONDS ), 'last_error' => $attempts >= 3 ? 'delivery_failed_max_attempts' : 'delivery_failed_retry_scheduled', 'updated_at_utc' => current_time( 'mysql', true ) ), array( 'id' => (int) $row['id'] ), array( '%s','%s','%s','%s' ), array( '%d' ) );
		}
	}
}

function parisan_core_register_reminder_admin_page() {
	add_submenu_page( 'edit.php?post_type=parisan_staff', __( 'یادآورهای نوبت', 'parisan-core' ), __( 'یادآورهای نوبت', 'parisan-core' ), 'edit_others_posts', 'parisan-booking-reminders', 'parisan_core_render_reminder_admin_page' );
}
add_action( 'admin_menu', 'parisan_core_register_reminder_admin_page' );

function parisan_core_handle_reminder_consent_revocation() {
	if ( ! current_user_can( 'edit_others_posts' ) ) { wp_die( esc_html__( 'اجازه تغییر رضایت ایمیل را ندارید.', 'parisan-core' ), '', array( 'response' => 403 ) ); }
	$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
	check_admin_referer( 'parisan_revoke_reminder_consent_' . $booking_id );
	global $wpdb;
	$bookings = $wpdb->prefix . 'parisan_bookings';
	$booking = $wpdb->get_row( $wpdb->prepare( "SELECT staff_id FROM {$bookings} WHERE id=%d", $booking_id ), ARRAY_A );
	if ( ! is_array( $booking ) || ! current_user_can( 'edit_post', (int) $booking['staff_id'] ) ) { wp_die( esc_html__( 'اجازه تغییر این رزرو را ندارید.', 'parisan-core' ), '', array( 'response' => 403 ) ); }
	$updated = $wpdb->update( $bookings, array( 'customer_email_consent' => 0, 'updated_at_utc' => current_time( 'mysql', true ) ), array( 'id' => $booking_id ), array( '%d','%s' ), array( '%d' ) );
	if ( false === $updated ) { wp_die( esc_html__( 'لغو رضایت ایمیل انجام نشد.', 'parisan-core' ), '', array( 'response' => 503 ) ); }
	parisan_core_skip_booking_reminders( $booking_id, 'email_consent_revoked' );
	wp_safe_redirect( add_query_arg( array( 'post_type' => 'parisan_staff', 'page' => 'parisan-booking-reminders', 'consent_revoked' => 1 ), admin_url( 'edit.php' ) ) );
	exit;
}
add_action( 'admin_post_parisan_revoke_reminder_consent', 'parisan_core_handle_reminder_consent_revocation' );

function parisan_core_render_reminder_admin_page() {
	if ( ! current_user_can( 'edit_others_posts' ) ) { wp_die( esc_html__( 'اجازه مشاهده وضعیت یادآورها را ندارید.', 'parisan-core' ), '', array( 'response' => 403 ) ); }
	global $wpdb;
	$queue = $wpdb->prefix . 'parisan_booking_reminders';
	$rows = $wpdb->get_results( "SELECT r.*,b.customer_name,b.customer_email,b.customer_email_consent,b.starts_at_utc FROM {$queue} r LEFT JOIN {$wpdb->prefix}parisan_bookings b ON b.id=r.booking_id ORDER BY r.scheduled_at_utc DESC LIMIT 200", ARRAY_A );
	$labels = array( 'pending'=>'در انتظار ارسال', 'sending'=>'در حال ارسال', 'sent'=>'ارسال‌شده', 'failed'=>'ناموفق', 'skipped'=>'ردشده' );
	echo '<div class="wrap" dir="rtl"><h1>وضعیت یادآورهای نوبت</h1><p>یادآورها فقط برای مشتریانی ساخته می‌شوند که هنگام رزرو، رضایت صریح دریافت ایمیل یادآوری را ثبت کرده باشند. ارسال به زیرساخت ایمیل WordPress (wp_mail) وابسته است؛ این بخش پیامک ارسال نمی‌کند.</p>';
	echo '<table class="widefat striped"><thead><tr><th>مشتری</th><th>ایمیل</th><th>زمان نوبت (UTC)</th><th>نوع یادآور</th><th>زمان برنامه‌ریزی (UTC)</th><th>وضعیت</th><th>تلاش</th><th>رضایت</th></tr></thead><tbody>';
	foreach ( (array) $rows as $row ) {
		echo '<tr><td>' . esc_html( $row['customer_name'] ) . '</td><td dir="ltr">' . esc_html( $row['customer_email'] ) . '</td><td>' . esc_html( $row['starts_at_utc'] ) . '</td><td>' . esc_html( '24h' === $row['reminder_kind'] ? '۲۴ ساعت قبل' : '۲ ساعت قبل' ) . '</td><td>' . esc_html( $row['scheduled_at_utc'] ) . '</td><td>' . esc_html( $labels[ $row['status'] ] ?? $row['status'] ) . '</td><td>' . esc_html( (int) $row['attempts'] ) . '</td><td>';
		if ( 1 === (int) $row['customer_email_consent'] ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="parisan_revoke_reminder_consent"><input type="hidden" name="booking_id" value="' . esc_attr( $row['booking_id'] ) . '">';
			wp_nonce_field( 'parisan_revoke_reminder_consent_' . (int) $row['booking_id'] );
			echo '<button class="button button-small" type="submit">لغو رضایت ایمیل</button></form>';
		}
		echo '</td></tr>';
	}
	if ( ! $rows ) { echo '<tr><td colspan="8">یادآوری‌ای ثبت نشده است.</td></tr>'; }
	echo '</tbody></table></div>';
}
