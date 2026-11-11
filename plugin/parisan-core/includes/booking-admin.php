<?php
/**
 * Staff-facing booking operations in WordPress admin.
 *
 * @package ParisanCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the booking operations screen below Specialists.
 *
 * @return void
 */
function parisan_core_register_booking_admin_page() {
	add_submenu_page(
		'edit.php?post_type=parisan_staff',
		__( 'مدیریت نوبت‌ها', 'parisan-core' ),
		__( 'مدیریت نوبت‌ها', 'parisan-core' ),
		'edit_others_posts',
		'parisan-bookings',
		'parisan_core_render_booking_admin_page'
	);
}
add_action( 'admin_menu', 'parisan_core_register_booking_admin_page' );

/**
 * Load the slot picker only on the booking operations screen.
 *
 * @param string $hook_suffix Current admin screen hook.
 * @return void
 */
function parisan_core_enqueue_booking_admin_assets( $hook_suffix ) {
	if ( false === strpos( (string) $hook_suffix, 'parisan-bookings' ) ) {
		return;
	}
	wp_register_style( 'parisan-booking-admin', false, array(), PARISAN_CORE_VERSION );
	wp_enqueue_style( 'parisan-booking-admin' );
	wp_add_inline_style( 'parisan-booking-admin', '.parisan-calendar-heading{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin:22px 0 12px}.parisan-calendar-heading h2{margin:0}.parisan-calendar-heading h2 span{font-size:13px;font-weight:400;color:#646970}.parisan-calendar-lanes{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px}.parisan-calendar-lane{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:14px;min-width:0}.parisan-calendar-lane h3{display:flex;justify-content:space-between;gap:12px;margin:0 0 12px;padding-bottom:10px;border-bottom:1px solid #eee}.parisan-calendar-lane h3 small{font-weight:400;color:#646970}.parisan-calendar-lane ol{list-style:none;margin:0;padding:0;display:grid;gap:8px}.parisan-calendar-lane li{display:grid;grid-template-columns:92px minmax(0,1fr);gap:4px 10px;padding:10px;border:1px solid #e2e4e7;border-right:4px solid #8c8f94;border-radius:7px;background:#f9f9f9}.parisan-calendar-lane li.is-pending{border-right-color:#dba617}.parisan-calendar-lane li.is-confirmed{border-right-color:#00a32a}.parisan-calendar-lane li.is-cancelled,.parisan-calendar-lane li.is-expired{opacity:.7}.parisan-calendar-lane li time{grid-row:span 2;direction:ltr;text-align:right;font-weight:600;font-variant-numeric:tabular-nums}.parisan-calendar-lane li a{display:grid;gap:2px;text-decoration:none}.parisan-calendar-lane li a span,.parisan-calendar-lane li small{color:#646970;font-size:12px}.parisan-calendar-lane li small{grid-column:2;overflow-wrap:anywhere}.parisan-booking-filters{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.parisan-booking-filters label{font-weight:600}.parisan-booking-filters select,.parisan-booking-filters input{max-width:190px}@media(max-width:782px){.parisan-calendar-lane li{grid-template-columns:78px minmax(0,1fr)}.parisan-calendar-heading nav{display:flex;gap:6px}.parisan-booking-filters>*{max-width:100%;width:100%}}' );
	wp_enqueue_script(
		'parisan-booking-admin',
		plugins_url( 'assets/js/booking-admin.js', PARISAN_CORE_FILE ),
		array(),
		PARISAN_CORE_VERSION,
		true
	);
	wp_localize_script(
		'parisan-booking-admin',
		'ParisanBookingAdmin',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'parisan_admin_available_slots' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'parisan_core_enqueue_booking_admin_assets' );

/**
 * Return advisory available start times to an authorized staff user.
 *
 * @return void
 */
function parisan_core_handle_admin_available_slots() {
	check_ajax_referer( 'parisan_admin_available_slots', 'nonce' );
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'اجازه مشاهده زمان‌های آزاد را ندارید.', 'parisan-core' ) ), 403 );
	}

	$staff_id = isset( $_POST['staff_id'] ) && is_scalar( $_POST['staff_id'] ) ? absint( wp_unslash( $_POST['staff_id'] ) ) : 0;
	$service_id = isset( $_POST['service_id'] ) && is_scalar( $_POST['service_id'] ) ? absint( wp_unslash( $_POST['service_id'] ) ) : 0;
	$date = isset( $_POST['date'] ) && is_string( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
	if ( ! $staff_id || ! $service_id || ! preg_match( '/^\\d{4}-\\d{2}-\\d{2}$/D', $date ) || ! current_user_can( 'edit_post', $staff_id ) || ! current_user_can( 'edit_post', $service_id ) ) {
		wp_send_json_error( array( 'message' => __( 'متخصص، خدمت یا تاریخ انتخاب‌شده معتبر نیست.', 'parisan-core' ) ), 400 );
	}

	$slots = parisan_core_get_available_slots( $staff_id, $service_id, $date );
	if ( is_wp_error( $slots ) ) {
		wp_send_json_error( array( 'message' => $slots->get_error_message() ), 400 );
	}
	$starts = array();
	$now_utc = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
	foreach ( $slots as $slot ) {
		if ( ! isset( $slot['starts_at_local'], $slot['starts_at_utc'] ) ) {
			continue;
		}
		$slot_utc = new DateTimeImmutable( $slot['starts_at_utc'], new DateTimeZone( 'UTC' ) );
		if ( $slot_utc > $now_utc ) {
			$starts[] = $slot['starts_at_local'];
		}
	}
	wp_send_json_success( array( 'slots' => $starts ) );
}
add_action( 'wp_ajax_parisan_admin_available_slots', 'parisan_core_handle_admin_available_slots' );

/**
 * Handle a staff-facing booking state transition.
 *
 * @return void
 */
function parisan_core_handle_booking_admin_transition() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'اجازه انجام این عملیات را ندارید.', 'parisan-core' ), 403 );
	}
	$booking_id = isset( $_POST['booking_id'] ) && is_scalar( $_POST['booking_id'] ) ? absint( wp_unslash( $_POST['booking_id'] ) ) : 0;
	check_admin_referer( 'parisan_transition_booking_' . $booking_id, 'parisan_booking_nonce' );

	$to_status = isset( $_POST['to_status'] ) && is_string( $_POST['to_status'] ) ? sanitize_key( wp_unslash( $_POST['to_status'] ) ) : '';
	$event_note = isset( $_POST['booking_event_note'] ) && is_string( $_POST['booking_event_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['booking_event_note'] ) ) : '';
	$event_note_length = function_exists( 'mb_strlen' ) ? mb_strlen( $event_note, 'UTF-8' ) : strlen( $event_note );
	if ( $event_note_length > 1000 ) { $event_note = ''; }
	if ( in_array( $to_status, array( 'cancelled', 'no_show' ), true ) && '' === trim( $event_note ) ) {
		$result = new WP_Error( 'parisan_booking_reason_required', __( 'برای لغو یا ثبت عدم مراجعه، درج دلیل الزامی است.', 'parisan-core' ) );
	} else {
		$result = parisan_core_transition_booking( $booking_id, $to_status, $event_note );
	}
	$redirect_args = array( 'page' => 'parisan-bookings' );
	if ( is_wp_error( $result ) ) {
		$redirect_args['booking_error'] = $result->get_error_code();
	} else {
		$redirect_args['booking_updated'] = 1;
	}
	if ( isset( $_POST['booking_status_filter'] ) && is_string( $_POST['booking_status_filter'] ) ) {
		$filter = sanitize_key( wp_unslash( $_POST['booking_status_filter'] ) );
		if ( in_array( $filter, array( 'pending', 'confirmed', 'cancelled', 'expired', 'completed', 'no_show' ), true ) ) {
			$redirect_args['status'] = $filter;
		}
	}
	if ( isset( $_POST['booking_detail_id'] ) && is_scalar( $_POST['booking_detail_id'] ) ) {
		$detail_id = absint( wp_unslash( $_POST['booking_detail_id'] ) );
		if ( $detail_id === $booking_id ) {
			$redirect_args['booking_id'] = $detail_id;
		}
	}
	wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'edit.php?post_type=parisan_staff' ) ) );
	exit;
}
add_action( 'admin_post_parisan_transition_booking', 'parisan_core_handle_booking_admin_transition' );

/**
 * Handle a staff-created booking from the admin screen.
 *
 * @return void
 */
function parisan_core_handle_manual_booking_create() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'اجازه انجام این عملیات را ندارید.', 'parisan-core' ), 403 );
	}
	check_admin_referer( 'parisan_create_manual_booking', 'parisan_manual_booking_nonce' );

	$staff_id = isset( $_POST['staff_id'] ) && is_scalar( $_POST['staff_id'] ) ? absint( wp_unslash( $_POST['staff_id'] ) ) : 0;
	$service_id = isset( $_POST['service_id'] ) && is_scalar( $_POST['service_id'] ) ? absint( wp_unslash( $_POST['service_id'] ) ) : 0;
	$booking_date = isset( $_POST['booking_date'] ) && is_string( $_POST['booking_date'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_date'] ) ) : '';
	$booking_time = isset( $_POST['booking_time'] ) && is_string( $_POST['booking_time'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_time'] ) ) : '';
	$local_start = $booking_date . ' ' . $booking_time;
	$name = isset( $_POST['customer_name'] ) && is_string( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '';
	$phone = isset( $_POST['customer_phone'] ) && is_string( $_POST['customer_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_phone'] ) ) : '';
	$email = isset( $_POST['customer_email'] ) && is_string( $_POST['customer_email'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['customer_email'] ) ) ) : '';
	$token = isset( $_POST['idempotency_token'] ) && is_string( $_POST['idempotency_token'] ) ? sanitize_text_field( wp_unslash( $_POST['idempotency_token'] ) ) : '';

	if ( ! $staff_id || ! $service_id || 'parisan_staff' !== get_post_type( $staff_id ) || 'parisan_service' !== get_post_type( $service_id ) || ! current_user_can( 'edit_post', $staff_id ) || ! current_user_can( 'edit_post', $service_id ) ) {
		wp_die( esc_html__( 'خدمت یا متخصص انتخاب‌شده معتبر نیست یا اجازه ویرایش آن را ندارید.', 'parisan-core' ), 403 );
	}
	if ( ! preg_match( '/^\\d{4}-\\d{2}-\\d{2}$/D', $booking_date ) || ! preg_match( '/^(?:[01]\\d|2[0-3]):[0-5]\\d$/D', $booking_time ) ) {
		$redirect_args = array( 'page' => 'parisan-bookings', 'booking_error' => 'parisan_invalid_booking_input' );
		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'edit.php?post_type=parisan_staff' ) ) );
		exit;
	}

	$result = parisan_core_create_booking(
		array(
			'staff_id' => $staff_id,
			'service_id' => $service_id,
			'starts_at_local' => $local_start,
			'customer_name' => $name,
			'customer_phone' => $phone,
			'customer_email' => $email,
			'idempotency_token' => $token,
		)
	);
	$redirect_args = array( 'page' => 'parisan-bookings' );
	if ( is_wp_error( $result ) ) {
		$redirect_args['booking_error'] = $result->get_error_code();
	} else {
		$redirect_args['booking_created'] = 1;
	}
	wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'edit.php?post_type=parisan_staff' ) ) );
	exit;
}
add_action( 'admin_post_parisan_create_manual_booking', 'parisan_core_handle_manual_booking_create' );

/**
 * Handle an authorized booking reschedule from the detail screen.
 *
 * @return void
 */
function parisan_core_handle_booking_reschedule() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'اجازه انجام این عملیات را ندارید.', 'parisan-core' ), 403 );
	}
	$booking_id = isset( $_POST['booking_id'] ) && is_scalar( $_POST['booking_id'] ) ? absint( wp_unslash( $_POST['booking_id'] ) ) : 0;
	check_admin_referer( 'parisan_reschedule_booking_' . $booking_id, 'parisan_reschedule_nonce' );
	$new_start = isset( $_POST['new_start_local'] ) && is_string( $_POST['new_start_local'] ) ? sanitize_text_field( wp_unslash( $_POST['new_start_local'] ) ) : '';
	if ( ! preg_match( '/^\\d{4}-\\d{2}-\\d{2}T(?:[01]\\d|2[0-3]):[0-5]\\d$/D', $new_start ) ) {
		$result = new WP_Error( 'parisan_invalid_reschedule_input', __( 'تاریخ یا ساعت جدید معتبر نیست.', 'parisan-core' ) );
	} else {
		$result = parisan_core_reschedule_booking( $booking_id, str_replace( 'T', ' ', $new_start ) );
	}
	$redirect_args = array( 'page' => 'parisan-bookings', 'booking_id' => $booking_id );
	if ( is_wp_error( $result ) ) {
		$redirect_args['booking_error'] = $result->get_error_code();
	} else {
		$redirect_args['booking_rescheduled'] = 1;
	}
	wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'edit.php?post_type=parisan_staff' ) ) );
	exit;
}
add_action( 'admin_post_parisan_reschedule_booking', 'parisan_core_handle_booking_reschedule' );

/**
 * Render one booking and its audit timeline.
 *
 * @param int $booking_id Booking row ID.
 * @return void
 */
function parisan_core_render_booking_detail( $booking_id ) {
	global $wpdb;

	$bookings_table = $wpdb->prefix . 'parisan_bookings';
	$events_table = $wpdb->prefix . 'parisan_booking_events';
	$booking = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT b.*, service_post.post_title AS service_title, staff_post.post_title AS staff_title, resource_post.post_title AS resource_title
			FROM {$bookings_table} b
			LEFT JOIN {$wpdb->posts} service_post ON service_post.ID = b.service_id
			LEFT JOIN {$wpdb->posts} staff_post ON staff_post.ID = b.staff_id
			LEFT JOIN {$wpdb->posts} resource_post ON resource_post.ID = b.resource_id
			WHERE b.id = %d",
			$booking_id
		),
		ARRAY_A
	);
	if ( ! is_array( $booking ) ) {
		wp_die( esc_html__( 'نوبت موردنظر پیدا نشد.', 'parisan-core' ), 404 );
	}
	if ( ! current_user_can( 'edit_post', (int) $booking['staff_id'] ) ) {
		wp_die( esc_html__( 'اجازه مشاهده این نوبت را ندارید.', 'parisan-core' ), 403 );
	}

	$events = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT e.*, users.display_name AS actor_name
			FROM {$events_table} e
			LEFT JOIN {$wpdb->users} users ON users.ID = e.actor_user_id
			WHERE e.booking_id = %d
			ORDER BY e.created_at_utc ASC, e.id ASC",
			$booking_id
		),
		ARRAY_A
	);
	$status_labels = array(
		'pending' => __( 'در انتظار تأیید', 'parisan-core' ),
		'confirmed' => __( 'تأییدشده', 'parisan-core' ),
		'cancelled' => __( 'لغوشده', 'parisan-core' ),
		'expired' => __( 'منقضی‌شده', 'parisan-core' ),
		'completed' => __( 'انجام‌شده', 'parisan-core' ),
		'no_show' => __( 'عدم مراجعه', 'parisan-core' ),
	);
	$event_labels = array(
		'created' => __( 'ثبت نوبت', 'parisan-core' ),
		'status_changed' => __( 'تغییر وضعیت', 'parisan-core' ),
		'rescheduled' => __( 'تغییر زمان', 'parisan-core' ),
	);
	$assigned_resource_ids = parisan_core_get_booking_resource_ids( $booking_id );
	$assigned_resource_names = array();
	foreach ( $assigned_resource_ids as $assigned_resource_id ) {
		$assigned_resource_names[] = get_the_title( $assigned_resource_id );
	}
	$assigned_resource_label = $assigned_resource_names ? implode( '، ', array_filter( $assigned_resource_names ) ) : __( 'بدون منبع اختصاصی', 'parisan-core' );
	$start = ( new DateTimeImmutable( $booking['starts_at_utc'], new DateTimeZone( 'UTC' ) ) )->setTimezone( wp_timezone() );
	$end = ( new DateTimeImmutable( $booking['ends_at_utc'], new DateTimeZone( 'UTC' ) ) )->setTimezone( wp_timezone() );
	$price_toman = intdiv( (int) $booking['price_amount'], 10 );
	$actions = array();
	if ( 'pending' === $booking['status'] ) {
		$actions = array( 'confirmed' => __( 'تأیید', 'parisan-core' ), 'cancelled' => __( 'لغو', 'parisan-core' ), 'expired' => __( 'انقضا', 'parisan-core' ) );
	} elseif ( 'confirmed' === $booking['status'] ) {
		$actions = array( 'cancelled' => __( 'لغو', 'parisan-core' ), 'completed' => __( 'انجام شد', 'parisan-core' ), 'no_show' => __( 'عدم مراجعه', 'parisan-core' ) );
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'جزئیات نوبت', 'parisan-core' ); ?></h1>
		<?php if ( isset( $_GET['booking_rescheduled'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'زمان نوبت با موفقیت تغییر کرد.', 'parisan-core' ); ?></p></div>
		<?php elseif ( isset( $_GET['booking_updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'وضعیت نوبت به‌روزرسانی شد.', 'parisan-core' ); ?></p></div>
		<?php elseif ( isset( $_GET['booking_error'] ) ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'تغییر وضعیت نوبت انجام نشد؛ وضعیت و دسترسی را بررسی کنید.', 'parisan-core' ); ?></p></div>
		<?php endif; ?>
		<p><a href="<?php echo esc_url( add_query_arg( array( 'post_type' => 'parisan_staff', 'page' => 'parisan-bookings' ), admin_url( 'edit.php' ) ) ); ?>"><?php esc_html_e( 'بازگشت به فهرست نوبت‌ها', 'parisan-core' ); ?></a></p>
		<h2><?php echo esc_html( $booking['customer_name'] ); ?></h2>
		<table class="widefat striped" style="max-width:900px"><tbody>
			<tr><th><?php esc_html_e( 'شماره تماس', 'parisan-core' ); ?></th><td dir="ltr"><?php echo esc_html( $booking['customer_phone'] ); ?></td></tr>
			<tr><th><?php esc_html_e( 'ایمیل', 'parisan-core' ); ?></th><td><?php echo esc_html( $booking['customer_email'] ? $booking['customer_email'] : '—' ); ?></td></tr>
			<tr><th><?php esc_html_e( 'خدمت', 'parisan-core' ); ?></th><td><?php echo esc_html( $booking['service_title'] ? $booking['service_title'] : '—' ); ?></td></tr>
			<tr><th><?php esc_html_e( 'متخصص', 'parisan-core' ); ?></th><td><?php echo esc_html( $booking['staff_title'] ? $booking['staff_title'] : '—' ); ?></td></tr>
			<tr><th><?php esc_html_e( 'منبع اختصاص‌یافته', 'parisan-core' ); ?></th><td><?php echo esc_html( $assigned_resource_label ); ?></td></tr>
			<tr><th><?php esc_html_e( 'زمان', 'parisan-core' ); ?></th><td><?php echo esc_html( wp_date( 'Y/m/d H:i', $start->getTimestamp(), wp_timezone() ) . ' تا ' . wp_date( 'H:i', $end->getTimestamp(), wp_timezone() ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'مبلغ ثبت‌شده', 'parisan-core' ); ?></th><td><?php echo esc_html( number_format_i18n( $price_toman ) . ' تومان' ); ?></td></tr>
			<tr><th><?php esc_html_e( 'وضعیت', 'parisan-core' ); ?></th><td><?php echo esc_html( isset( $status_labels[ $booking['status'] ] ) ? $status_labels[ $booking['status'] ] : $booking['status'] ); ?></td></tr>
		</tbody></table>

		<?php if ( in_array( $booking['status'], array( 'pending', 'confirmed' ), true ) ) : ?>
			<h2><?php esc_html_e( 'تغییر زمان نوبت', 'parisan-core' ); ?></h2>
			<p><?php esc_html_e( 'مدت ثبت‌شده و مبلغ نوبت حفظ می‌شود؛ زمان جدید فقط در صورت آزاد بودن ظرفیت متخصص و منبع موردنیاز خدمت ذخیره خواهد شد.', 'parisan-core' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="parisan_reschedule_booking">
				<input type="hidden" name="booking_id" value="<?php echo esc_attr( $booking['id'] ); ?>">
				<?php wp_nonce_field( 'parisan_reschedule_booking_' . $booking['id'], 'parisan_reschedule_nonce' ); ?>
				<label for="parisan-reschedule-start"><?php esc_html_e( 'تاریخ و ساعت جدید', 'parisan-core' ); ?></label>
				<input required type="datetime-local" id="parisan-reschedule-start" name="new_start_local" value="<?php echo esc_attr( wp_date( 'Y-m-d\\TH:i', $start->getTimestamp(), wp_timezone() ) ); ?>" min="<?php echo esc_attr( wp_date( 'Y-m-d', time(), wp_timezone() ) ); ?>">
				<p class="description"><?php echo esc_html( wp_timezone()->getName() ); ?></p>
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'بررسی و تغییر زمان', 'parisan-core' ); ?></button>
			</form>
		<?php endif; ?>

		<?php if ( $actions ) : ?>
			<h2><?php esc_html_e( 'عملیات مجاز', 'parisan-core' ); ?></h2>
			<?php foreach ( $actions as $target_status => $action_label ) : ?>
				<?php $confirm_action = 'cancelled' === $target_status ? "return confirm('" . esc_js( __( 'از لغو این نوبت مطمئن هستید؟', 'parisan-core' ) ) . "');" : ''; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin:0 6px 6px 0">
					<input type="hidden" name="action" value="parisan_transition_booking">
					<input type="hidden" name="booking_id" value="<?php echo esc_attr( $booking['id'] ); ?>">
					<input type="hidden" name="booking_detail_id" value="<?php echo esc_attr( $booking['id'] ); ?>">
					<input type="hidden" name="to_status" value="<?php echo esc_attr( $target_status ); ?>">
					<?php if ( in_array( $target_status, array( 'cancelled', 'no_show' ), true ) ) : ?>
						<label class="screen-reader-text" for="parisan-booking-reason-<?php echo esc_attr( $booking['id'] . '-' . $target_status ); ?>"><?php esc_html_e( 'دلیل لغو یا عدم مراجعه', 'parisan-core' ); ?></label>
						<input required maxlength="1000" type="text" name="booking_event_note" id="parisan-booking-reason-<?php echo esc_attr( $row['id'] . '-' . $target_status ); ?>" placeholder="<?php esc_attr_e( 'ثبت دلیل الزامی', 'parisan-core' ); ?>">
					<?php endif; ?>
					<?php wp_nonce_field( 'parisan_transition_booking_' . $booking['id'], 'parisan_booking_nonce' ); ?>
					<button type="submit" class="button <?php echo 'cancelled' === $target_status ? 'button-link-delete' : 'button-secondary'; ?>" onclick="<?php echo esc_attr( $confirm_action ); ?>"><?php echo esc_html( $action_label ); ?></button>
				</form>
			<?php endforeach; ?>
		<?php endif; ?>

		<h2><?php esc_html_e( 'سوابق و رویدادهای نوبت', 'parisan-core' ); ?></h2>
		<?php if ( $events ) : ?>
			<table class="widefat striped" style="max-width:1100px"><thead><tr>
				<th><?php esc_html_e( 'زمان', 'parisan-core' ); ?></th>
				<th><?php esc_html_e( 'رویداد', 'parisan-core' ); ?></th>
				<th><?php esc_html_e( 'وضعیت قبلی', 'parisan-core' ); ?></th>
				<th><?php esc_html_e( 'وضعیت جدید', 'parisan-core' ); ?></th>
				<th><?php esc_html_e( 'کاربر', 'parisan-core' ); ?></th>
				<th><?php esc_html_e( 'توضیح', 'parisan-core' ); ?></th>
			</tr></thead><tbody>
			<?php foreach ( $events as $event ) : ?>
				<?php $event_time = ( new DateTimeImmutable( $event['created_at_utc'], new DateTimeZone( 'UTC' ) ) )->setTimezone( wp_timezone() ); ?>
				<tr>
					<td><?php echo esc_html( wp_date( 'Y/m/d H:i:s', $event_time->getTimestamp(), wp_timezone() ) ); ?></td>
					<td><?php echo esc_html( isset( $event_labels[ $event['event_type'] ] ) ? $event_labels[ $event['event_type'] ] : $event['event_type'] ); ?></td>
					<td><?php echo esc_html( isset( $status_labels[ $event['from_status'] ] ) ? $status_labels[ $event['from_status'] ] : ( $event['from_status'] ? $event['from_status'] : '—' ) ); ?></td>
					<td><?php echo esc_html( isset( $status_labels[ $event['to_status'] ] ) ? $status_labels[ $event['to_status'] ] : ( $event['to_status'] ? $event['to_status'] : '—' ) ); ?></td>
					<td><?php echo esc_html( $event['actor_name'] ? $event['actor_name'] : __( 'سامانه', 'parisan-core' ) ); ?></td>
					<td><?php echo esc_html( $event['event_note'] ? $event['event_note'] : '—' ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
		<?php else : ?>
			<p><?php esc_html_e( 'رویدادی برای این نوبت ثبت نشده است.', 'parisan-core' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Render the paginated booking operations list.
 *
 * @return void
 */
function parisan_core_render_booking_admin_page() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'اجازه مشاهده این صفحه را ندارید.', 'parisan-core' ), 403 );
	}

	$detail_id = isset( $_GET['booking_id'] ) ? absint( wp_unslash( $_GET['booking_id'] ) ) : 0;
	if ( $detail_id ) {
		parisan_core_render_booking_detail( $detail_id );
		return;
	}

	$staff_options = get_posts( array( 'post_type' => 'parisan_staff', 'post_status' => 'publish', 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC' ) );
	$staff_options = array_values( array_filter( $staff_options, static function ( $person ) { return current_user_can( 'edit_post', $person->ID ); } ) );
	$service_options = get_posts( array( 'post_type' => 'parisan_service', 'post_status' => 'publish', 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC' ) );
	$service_options = array_values( array_filter( $service_options, static function ( $service ) { return current_user_can( 'edit_post', $service->ID ); } ) );
	$resource_options = get_posts( array( 'post_type' => 'parisan_resource', 'post_status' => 'publish', 'posts_per_page' => 300, 'orderby' => 'title', 'order' => 'ASC' ) );

	global $wpdb;
	$table = $wpdb->prefix . 'parisan_bookings';
	$local_now = new DateTimeImmutable( 'now', wp_timezone() );
	$today_start_utc = $local_now->setTime( 0, 0, 0 )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	$tomorrow_start_utc = $local_now->modify( '+1 day' )->setTime( 0, 0, 0 )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	$today_metrics = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT
				SUM( CASE WHEN b.starts_at_utc >= %s AND b.starts_at_utc < %s AND b.status IN ('pending','confirmed') THEN 1 ELSE 0 END ) AS today_active,
				SUM( CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END ) AS awaiting_confirmation,
				SUM( CASE WHEN b.starts_at_utc >= %s AND b.status = 'confirmed' THEN 1 ELSE 0 END ) AS upcoming_confirmed
			FROM {$table} b",
			$today_start_utc,
			$tomorrow_start_utc,
			$tomorrow_start_utc
		),
		ARRAY_A
	);
	$today_metrics = is_array( $today_metrics ) ? $today_metrics : array();
	$valid_statuses = array( 'pending', 'confirmed', 'cancelled', 'expired', 'completed', 'no_show' );
	$status_filter = isset( $_GET['status'] ) && is_string( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
	if ( ! in_array( $status_filter, $valid_statuses, true ) ) {
		$status_filter = '';
	}
	$booking_date_filter = isset( $_GET['booking_date'] ) && is_string( $_GET['booking_date'] ) ? sanitize_text_field( wp_unslash( $_GET['booking_date'] ) ) : '';
	$booking_date_value = null;
	if ( '' !== $booking_date_filter ) {
		$booking_date_value = DateTimeImmutable::createFromFormat( '!Y-m-d', $booking_date_filter, wp_timezone() );
		$date_errors = DateTimeImmutable::getLastErrors();
		if ( false === $booking_date_value || ( is_array( $date_errors ) && ( $date_errors['warning_count'] || $date_errors['error_count'] ) ) || $booking_date_value->format( 'Y-m-d' ) !== $booking_date_filter ) {
			$booking_date_filter = '';
			$booking_date_value = null;
		}
	}
	$staff_filter = isset( $_GET['staff_id'] ) ? absint( wp_unslash( $_GET['staff_id'] ) ) : 0;
	if ( $staff_filter && ( 'parisan_staff' !== get_post_type( $staff_filter ) || ! current_user_can( 'edit_post', $staff_filter ) ) ) {
		$staff_filter = 0;
	}
	$resource_filter = isset( $_GET['resource_id'] ) ? absint( wp_unslash( $_GET['resource_id'] ) ) : 0;
	if ( $resource_filter && 'parisan_resource' !== get_post_type( $resource_filter ) ) {
		$resource_filter = 0;
	}
	$paged = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
	$per_page = 30;
	$offset = ( $paged - 1 ) * $per_page;
	$where_parts = array();
	$count_args = array();
	if ( '' !== $status_filter ) {
		$where_parts[] = 'b.status = %s';
		$count_args[] = $status_filter;
	}
	if ( $booking_date_value instanceof DateTimeImmutable ) {
		$date_start_utc = $booking_date_value->setTime( 0, 0, 0 )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$date_end_utc = $booking_date_value->modify( '+1 day' )->setTime( 0, 0, 0 )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$where_parts[] = 'b.starts_at_utc >= %s AND b.starts_at_utc < %s';
		$count_args[] = $date_start_utc;
		$count_args[] = $date_end_utc;
	}
	if ( $staff_filter ) {
		$where_parts[] = 'b.staff_id = %d';
		$count_args[] = $staff_filter;
	}
	if ( $resource_filter ) {
		$where_parts[] = '( EXISTS ( SELECT 1 FROM ' . $wpdb->prefix . 'parisan_booking_resources calendar_br WHERE calendar_br.booking_id = b.id AND calendar_br.resource_id = %d ) OR ( b.resource_id = %d AND NOT EXISTS ( SELECT 1 FROM ' . $wpdb->prefix . 'parisan_booking_resources legacy_br WHERE legacy_br.booking_id = b.id ) ) )';
		$count_args[] = $resource_filter;
		$count_args[] = $resource_filter;
	}
	$where = $where_parts ? ' WHERE ' . implode( ' AND ', $where_parts ) : '';
	$count_sql = "SELECT COUNT(*) FROM {$table} b{$where}";
	$total = (int) $wpdb->get_var( $count_args ? $wpdb->prepare( $count_sql, $count_args ) : $count_sql );
	$total_pages = max( 1, (int) ceil( $total / $per_page ) );

	$sql = "SELECT b.id, b.service_id, b.staff_id, b.resource_id, b.customer_name, b.customer_phone, b.starts_at_utc, b.ends_at_utc, b.status, b.price_amount, b.currency_code, service_post.post_title AS service_title, staff_post.post_title AS staff_title, resource_post.post_title AS resource_title
		FROM {$table} b
		LEFT JOIN {$wpdb->posts} service_post ON service_post.ID = b.service_id
		LEFT JOIN {$wpdb->posts} staff_post ON staff_post.ID = b.staff_id
		LEFT JOIN {$wpdb->posts} resource_post ON resource_post.ID = b.resource_id{$where}
		ORDER BY b.starts_at_utc " . ( $booking_date_value instanceof DateTimeImmutable ? 'ASC' : 'DESC' ) . "
		LIMIT %d OFFSET %d";
	$query_args = $count_args;
	$query_args[] = $per_page;
	$query_args[] = $offset;
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $query_args ), ARRAY_A );
	$calendar_rows = array();
	if ( $booking_date_value instanceof DateTimeImmutable ) {
		$calendar_sql = "SELECT b.id, b.staff_id, b.service_id, b.resource_id, b.customer_name, b.customer_phone, b.starts_at_utc, b.ends_at_utc, b.status, service_post.post_title AS service_title, staff_post.post_title AS staff_title FROM {$table} b LEFT JOIN {$wpdb->posts} service_post ON service_post.ID = b.service_id LEFT JOIN {$wpdb->posts} staff_post ON staff_post.ID = b.staff_id{$where} ORDER BY b.starts_at_utc ASC";
		$calendar_rows = $wpdb->get_results( $count_args ? $wpdb->prepare( $calendar_sql, $count_args ) : $calendar_sql, ARRAY_A );
		$calendar_rows = is_array( $calendar_rows ) ? $calendar_rows : array();
		foreach ( $calendar_rows as &$calendar_row ) {
			$calendar_resource_names = array();
			foreach ( parisan_core_get_booking_resource_ids( (int) $calendar_row['id'] ) as $calendar_resource_id ) {
				$calendar_resource_names[] = get_the_title( $calendar_resource_id );
			}
			$calendar_row['resource_label'] = $calendar_resource_names ? implode( '، ', array_filter( $calendar_resource_names ) ) : '—';
		}
		unset( $calendar_row );
	}
	if ( is_array( $rows ) ) {
		foreach ( $rows as &$booking_row ) {
			$assigned_ids = parisan_core_get_booking_resource_ids( (int) $booking_row['id'] );
			$assigned_names = array();
			foreach ( $assigned_ids as $assigned_id ) {
				$assigned_names[] = get_the_title( $assigned_id );
			}
			$booking_row['resource_label'] = $assigned_names ? implode( '، ', array_filter( $assigned_names ) ) : '—';
		}
		unset( $booking_row );
	}


	$status_labels = array(
		'pending' => __( 'در انتظار تأیید', 'parisan-core' ),
		'confirmed' => __( 'تأییدشده', 'parisan-core' ),
		'cancelled' => __( 'لغوشده', 'parisan-core' ),
		'expired' => __( 'منقضی‌شده', 'parisan-core' ),
		'completed' => __( 'انجام‌شده', 'parisan-core' ),
		'no_show' => __( 'عدم مراجعه', 'parisan-core' ),
	);
	$error_messages = array(
		'parisan_booking_reason_required' => __( 'برای لغو یا ثبت عدم مراجعه، درج دلیل الزامی است.', 'parisan-core' ),
		'parisan_booking_not_found' => __( 'رزرو موردنظر پیدا نشد.', 'parisan-core' ),
		'parisan_booking_transition_forbidden' => __( 'اجازه تغییر وضعیت این رزرو را ندارید.', 'parisan-core' ),
		'parisan_invalid_booking_transition' => __( 'این تغییر وضعیت در چرخه رزرو مجاز نیست.', 'parisan-core' ),
		'parisan_booking_confirmation_conflict' => __( 'رزرو با نوبت یا عدم دسترسی دیگری تداخل دارد.', 'parisan-core' ),
		'parisan_booking_lock_unavailable' => __( 'تقویم متخصص در حال پردازش است؛ دوباره تلاش کنید.', 'parisan-core' ),
		'parisan_resource_lock_unavailable' => __( 'ظرفیت منابع در حال پردازش است؛ دوباره تلاش کنید.', 'parisan-core' ),
		'parisan_invalid_booking_input' => __( 'اطلاعات نوبت کامل یا معتبر نیست.', 'parisan-core' ),
		'parisan_invalid_local_booking_time' => __( 'زمان محلی واردشده معتبر نیست.', 'parisan-core' ),
		'parisan_booking_time_in_past' => __( 'زمان رزرو باید در آینده باشد.', 'parisan-core' ),
		'parisan_booking_slot_unavailable' => __( 'این زمان در تقویم متخصص آزاد نیست.', 'parisan-core' ),
		'parisan_service_not_available_for_staff' => __( 'این خدمت برای متخصص انتخاب‌شده تعریف نشده است.', 'parisan-core' ),
		'parisan_service_requires_price_confirmation' => __( 'برای این خدمت ابتدا باید قیمت نهایی مشخص شود.', 'parisan-core' ),
		'parisan_transaction_storage_unavailable' => __( 'ذخیره‌ساز امن رزرو آماده نیست.', 'parisan-core' ),
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'مدیریت نوبت‌ها', 'parisan-core' ); ?></h1>
		<p><?php esc_html_e( 'نوبت‌های ثبت‌شده را بررسی کنید و وضعیت آن‌ها را از مسیر مجاز چرخه رزرو تغییر دهید. زمان‌ها بر اساس منطقه زمانی تنظیم‌شده در وردپرس نمایش داده می‌شوند.', 'parisan-core' ); ?></p>
		<section class="parisan-booking-overview" aria-label="<?php esc_attr_e( 'خلاصه عملیات نوبت‌ها', 'parisan-core' ); ?>">
			<article class="parisan-booking-metric"><span><?php esc_html_e( 'نوبت‌های فعال امروز', 'parisan-core' ); ?></span><strong><?php echo esc_html( number_format_i18n( absint( $today_metrics['today_active'] ?? 0 ) ) ); ?></strong><small><?php echo esc_html( wp_date( 'l، j F Y', $local_now->getTimestamp(), wp_timezone() ) ); ?></small></article>
			<article class="parisan-booking-metric is-attention"><span><?php esc_html_e( 'در انتظار تأیید', 'parisan-core' ); ?></span><strong><?php echo esc_html( number_format_i18n( absint( $today_metrics['awaiting_confirmation'] ?? 0 ) ) ); ?></strong><a href="<?php echo esc_url( add_query_arg( array( 'post_type' => 'parisan_staff', 'page' => 'parisan-bookings', 'status' => 'pending' ), admin_url( 'edit.php' ) ) ); ?>"><?php esc_html_e( 'مشاهده موارد', 'parisan-core' ); ?></a></article>
			<article class="parisan-booking-metric"><span><?php esc_html_e( 'نوبت‌های تأییدشده آینده', 'parisan-core' ); ?></span><strong><?php echo esc_html( number_format_i18n( absint( $today_metrics['upcoming_confirmed'] ?? 0 ) ) ); ?></strong><small><?php esc_html_e( 'از ابتدای فردا به بعد', 'parisan-core' ); ?></small></article>
		</section>

		<?php if ( isset( $_GET['booking_created'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'نوبت با موفقیت ثبت شد و در وضعیت «در انتظار تأیید» قرار گرفت.', 'parisan-core' ); ?></p></div>
		<?php elseif ( isset( $_GET['booking_updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'وضعیت نوبت به‌روزرسانی شد.', 'parisan-core' ); ?></p></div>
		<?php elseif ( isset( $_GET['booking_error'] ) ) : ?>
			<?php $error_code = sanitize_key( wp_unslash( $_GET['booking_error'] ) ); ?>
			<div class="notice notice-error"><p><?php echo esc_html( isset( $error_messages[ $error_code ] ) ? $error_messages[ $error_code ] : __( 'تغییر وضعیت نوبت انجام نشد.', 'parisan-core' ) ); ?></p></div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'ثبت نوبت دستی', 'parisan-core' ); ?></h2>
		<p><?php esc_html_e( 'ثبت نوبت از طرف مشتری توسط پذیرش. ظرفیت و زمان انتخاب‌شده هنگام ذخیره دوباره بررسی می‌شود و نوبت جدید ابتدا در وضعیت انتظار تأیید قرار می‌گیرد.', 'parisan-core' ); ?></p>
		<?php if ( $staff_options && $service_options ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="parisan_create_manual_booking">
				<?php wp_nonce_field( 'parisan_create_manual_booking', 'parisan_manual_booking_nonce' ); ?>
				<input type="hidden" name="idempotency_token" value="<?php echo esc_attr( wp_generate_uuid4() ); ?>">
				<table class="form-table" role="presentation"><tbody>
					<tr><th scope="row"><label for="parisan-manual-staff"><?php esc_html_e( 'متخصص', 'parisan-core' ); ?></label></th><td><select required id="parisan-manual-staff" name="staff_id"><option value=""><?php esc_html_e( 'انتخاب متخصص', 'parisan-core' ); ?></option><?php foreach ( $staff_options as $person ) : ?><option value="<?php echo esc_attr( $person->ID ); ?>"><?php echo esc_html( get_the_title( $person ) ); ?></option><?php endforeach; ?></select></td></tr>
					<tr><th scope="row"><label for="parisan-manual-service"><?php esc_html_e( 'خدمت', 'parisan-core' ); ?></label></th><td><select required id="parisan-manual-service" name="service_id"><option value=""><?php esc_html_e( 'انتخاب خدمت', 'parisan-core' ); ?></option><?php foreach ( $service_options as $service ) : ?><option value="<?php echo esc_attr( $service->ID ); ?>"><?php echo esc_html( get_the_title( $service ) ); ?></option><?php endforeach; ?></select></td></tr>
					<tr><th scope="row"><label for="parisan-manual-date"><?php esc_html_e( 'تاریخ', 'parisan-core' ); ?></label></th><td><input required type="date" id="parisan-manual-date" name="booking_date" min="<?php echo esc_attr( wp_date( 'Y-m-d', time(), wp_timezone() ) ); ?>"><p class="description"><?php echo esc_html( wp_timezone()->getName() ); ?></p></td></tr>
					<tr><th scope="row"><label for="parisan-manual-time"><?php esc_html_e( 'ساعت آزاد', 'parisan-core' ); ?></label></th><td><select required disabled id="parisan-manual-time" name="booking_time"><option value=""><?php esc_html_e( 'ابتدا تاریخ، متخصص و خدمت را انتخاب کنید', 'parisan-core' ); ?></option></select><p id="parisan-manual-slot-status" class="description" aria-live="polite"></p></td></tr>
					<tr><th scope="row"><label for="parisan-manual-customer"><?php esc_html_e( 'نام مشتری', 'parisan-core' ); ?></label></th><td><input required type="text" maxlength="190" class="regular-text" id="parisan-manual-customer" name="customer_name"></td></tr>
					<tr><th scope="row"><label for="parisan-manual-phone"><?php esc_html_e( 'شماره تماس', 'parisan-core' ); ?></label></th><td><input required type="tel" inputmode="tel" maxlength="24" class="regular-text" id="parisan-manual-phone" name="customer_phone" dir="ltr"></td></tr>
					<tr><th scope="row"><label for="parisan-manual-email"><?php esc_html_e( 'ایمیل (اختیاری)', 'parisan-core' ); ?></label></th><td><input type="email" maxlength="190" class="regular-text" id="parisan-manual-email" name="customer_email" dir="ltr"></td></tr>
				</tbody></table>
				<button type="submit" id="parisan-manual-submit" class="button button-primary" disabled><?php esc_html_e( 'ثبت نوبت در انتظار تأیید', 'parisan-core' ); ?></button>
			</form>
		<?php else : ?>
			<p><?php esc_html_e( 'برای ثبت نوبت، حداقل یک متخصص و یک خدمت قابل ویرایش لازم است.', 'parisan-core' ); ?></p>
		<?php endif; ?>

		<hr>
		<h2><?php esc_html_e( 'فهرست نوبت‌ها', 'parisan-core' ); ?></h2>
		<form method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>" class="parisan-booking-filters">
			<input type="hidden" name="post_type" value="parisan_staff">
			<input type="hidden" name="page" value="parisan-bookings">
			<label for="parisan-booking-date"><?php esc_html_e( 'برنامه روزانه', 'parisan-core' ); ?></label>
			<input type="date" id="parisan-booking-date" name="booking_date" value="<?php echo esc_attr( $booking_date_filter ); ?>">
			<label for="parisan-booking-staff"><?php esc_html_e( 'متخصص', 'parisan-core' ); ?></label>
			<select id="parisan-booking-staff" name="staff_id">
				<option value="0"><?php esc_html_e( 'همه متخصصان', 'parisan-core' ); ?></option>
				<?php foreach ( $staff_options as $person ) : ?>
					<option value="<?php echo esc_attr( $person->ID ); ?>" <?php selected( $staff_filter, $person->ID ); ?>><?php echo esc_html( get_the_title( $person ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<label for="parisan-booking-status"><?php esc_html_e( 'وضعیت نوبت', 'parisan-core' ); ?></label>
			<label for="parisan-booking-resource"><?php esc_html_e( 'منبع سالن', 'parisan-core' ); ?></label>
			<select id="parisan-booking-resource" name="resource_id">
				<option value="0"><?php esc_html_e( 'همه منابع', 'parisan-core' ); ?></option>
				<?php foreach ( $resource_options as $resource_option ) : ?>
					<option value="<?php echo esc_attr( $resource_option->ID ); ?>" <?php selected( $resource_filter, $resource_option->ID ); ?>><?php echo esc_html( get_the_title( $resource_option ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<select id="parisan-booking-status" name="status">
				<option value=""><?php esc_html_e( 'همه وضعیت‌ها', 'parisan-core' ); ?></option>
				<?php foreach ( $status_labels as $status => $label ) : ?>
					<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $status_filter, $status ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button button-secondary"><?php esc_html_e( 'نمایش برنامه', 'parisan-core' ); ?></button>
			<a class="button button-secondary" href="<?php echo esc_url( add_query_arg( array( 'post_type' => 'parisan_staff', 'page' => 'parisan-bookings' ), admin_url( 'edit.php' ) ) ); ?>"><?php esc_html_e( 'همه تاریخ‌ها', 'parisan-core' ); ?></a>
		</form>

		<?php if ( $booking_date_value instanceof DateTimeImmutable ) : ?>
			<?php $previous_day = $booking_date_value->modify( '-1 day' )->format( 'Y-m-d' ); $next_day = $booking_date_value->modify( '+1 day' )->format( 'Y-m-d' ); ?>
			<section class="parisan-booking-calendar" aria-labelledby="parisan-calendar-title">
				<div class="parisan-calendar-heading"><h2 id="parisan-calendar-title"><?php esc_html_e( 'تقویم عملیاتی روز', 'parisan-core' ); ?> <span><?php echo esc_html( wp_date( 'l، j F Y', $booking_date_value->getTimestamp(), wp_timezone() ) ); ?></span></h2>
					<nav aria-label="<?php esc_attr_e( 'جابجایی روز تقویم', 'parisan-core' ); ?>"><a class="button" href="<?php echo esc_url( add_query_arg( array( 'post_type' => 'parisan_staff', 'page' => 'parisan-bookings', 'booking_date' => $previous_day, 'staff_id' => $staff_filter, 'resource_id' => $resource_filter, 'status' => $status_filter ), admin_url( 'edit.php' ) ) ); ?>"><?php esc_html_e( 'روز قبل', 'parisan-core' ); ?></a> <a class="button" href="<?php echo esc_url( add_query_arg( array( 'post_type' => 'parisan_staff', 'page' => 'parisan-bookings', 'booking_date' => $next_day, 'staff_id' => $staff_filter, 'resource_id' => $resource_filter, 'status' => $status_filter ), admin_url( 'edit.php' ) ) ); ?>"><?php esc_html_e( 'روز بعد', 'parisan-core' ); ?></a></nav>
				</div>
				<?php if ( $calendar_rows ) : ?>
					<div class="parisan-calendar-lanes">
						<?php foreach ( $staff_options as $calendar_staff ) : ?>
							<?php $staff_day_rows = array_values( array_filter( $calendar_rows, static function ( $calendar_item ) use ( $calendar_staff ) { return (int) $calendar_item['staff_id'] === (int) $calendar_staff->ID; } ) ); if ( ! $staff_day_rows ) { continue; } ?>
							<section class="parisan-calendar-lane"><h3><?php echo esc_html( get_the_title( $calendar_staff ) ); ?><small><?php echo esc_html( sprintf( _n( '%d نوبت', '%d نوبت', count( $staff_day_rows ), 'parisan-core' ), count( $staff_day_rows ) ) ); ?></small></h3>
								<ol><?php foreach ( $staff_day_rows as $calendar_item ) : ?>
									<?php $calendar_start = ( new DateTimeImmutable( $calendar_item['starts_at_utc'], new DateTimeZone( 'UTC' ) ) )->setTimezone( wp_timezone() ); $calendar_end = ( new DateTimeImmutable( $calendar_item['ends_at_utc'], new DateTimeZone( 'UTC' ) ) )->setTimezone( wp_timezone() ); ?>
									<li class="is-<?php echo esc_attr( sanitize_html_class( $calendar_item['status'] ) ); ?>"><time><?php echo esc_html( $calendar_start->format( 'H:i' ) . ' – ' . $calendar_end->format( 'H:i' ) ); ?></time><a href="<?php echo esc_url( add_query_arg( array( 'post_type' => 'parisan_staff', 'page' => 'parisan-bookings', 'booking_id' => (int) $calendar_item['id'] ), admin_url( 'edit.php' ) ) ); ?>"><strong><?php echo esc_html( $calendar_item['customer_name'] ); ?></strong><span><?php echo esc_html( $calendar_item['service_title'] ? $calendar_item['service_title'] : '—' ); ?></span></a><small><?php echo esc_html( $calendar_item['resource_label'] ); ?> · <?php echo esc_html( isset( $status_labels[ $calendar_item['status'] ] ) ? $status_labels[ $calendar_item['status'] ] : $calendar_item['status'] ); ?></small></li>
								<?php endforeach; ?></ol>
							</section>
						<?php endforeach; ?>
					</div>
				<?php else : ?><p><?php esc_html_e( 'برای این روز و فیلترها نوبتی ثبت نشده است.', 'parisan-core' ); ?></p><?php endif; ?>
			</section>
		<?php endif; ?>

		<p><?php printf( esc_html__( 'تعداد نوبت‌ها: %d', 'parisan-core' ), $total ); ?></p>
		<?php if ( $rows ) : ?>
			<table class="widefat striped">
				<thead><tr>
					<th scope="col"><?php esc_html_e( 'زمان', 'parisan-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'مشتری', 'parisan-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'شماره تماس', 'parisan-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'خدمت', 'parisan-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'متخصص', 'parisan-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'منبع', 'parisan-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'مبلغ', 'parisan-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'وضعیت', 'parisan-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'عملیات', 'parisan-core' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<?php
					$start_utc = new DateTimeImmutable( $row['starts_at_utc'], new DateTimeZone( 'UTC' ) );
					$local_start = $start_utc->setTimezone( wp_timezone() );
					$local_end = ( new DateTimeImmutable( $row['ends_at_utc'], new DateTimeZone( 'UTC' ) ) )->setTimezone( wp_timezone() );
					$price_toman = intdiv( (int) $row['price_amount'], 10 );
					$actions = array();
					if ( 'pending' === $row['status'] ) {
						$actions = array( 'confirmed' => __( 'تأیید', 'parisan-core' ), 'cancelled' => __( 'لغو', 'parisan-core' ), 'expired' => __( 'انقضا', 'parisan-core' ) );
					} elseif ( 'confirmed' === $row['status'] ) {
						$actions = array( 'cancelled' => __( 'لغو', 'parisan-core' ), 'completed' => __( 'انجام شد', 'parisan-core' ), 'no_show' => __( 'عدم مراجعه', 'parisan-core' ) );
					}
					?>
					<tr>
						<td><?php echo esc_html( wp_date( 'Y/m/d', $local_start->getTimestamp(), wp_timezone() ) . ' ' . wp_date( 'H:i', $local_start->getTimestamp(), wp_timezone() ) . ' تا ' . wp_date( 'H:i', $local_end->getTimestamp(), wp_timezone() ) ); ?></td>
						<td><?php echo esc_html( $row['customer_name'] ); ?></td>
						<td dir="ltr"><?php echo esc_html( $row['customer_phone'] ); ?></td>
						<td><?php echo esc_html( $row['service_title'] ? $row['service_title'] : '—' ); ?></td>
						<td><?php echo esc_html( $row['staff_title'] ? $row['staff_title'] : '—' ); ?></td>
						<td><?php echo esc_html( ! empty( $row['resource_label'] ) ? $row['resource_label'] : '—' ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $price_toman ) . ' تومان' ); ?></td>
						<td><?php echo esc_html( isset( $status_labels[ $row['status'] ] ) ? $status_labels[ $row['status'] ] : $row['status'] ); ?></td>
						<td>
							<?php $detail_url = add_query_arg( array( 'post_type' => 'parisan_staff', 'page' => 'parisan-bookings', 'booking_id' => (int) $row['id'] ), admin_url( 'edit.php' ) ); ?>
							<a class="button button-secondary" href="<?php echo esc_url( $detail_url ); ?>"><?php esc_html_e( 'جزئیات', 'parisan-core' ); ?></a>
							<?php foreach ( $actions as $target_status => $action_label ) : ?>
								<?php $confirm_action = 'cancelled' === $target_status ? "return confirm('" . esc_js( __( 'از لغو این نوبت مطمئن هستید؟', 'parisan-core' ) ) . "');" : ''; ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin:0 0 4px 4px">
									<input type="hidden" name="action" value="parisan_transition_booking">
									<input type="hidden" name="booking_id" value="<?php echo esc_attr( $row['id'] ); ?>">
									<input type="hidden" name="to_status" value="<?php echo esc_attr( $target_status ); ?>">
									<input type="hidden" name="booking_status_filter" value="<?php echo esc_attr( $status_filter ); ?>">
									<?php wp_nonce_field( 'parisan_transition_booking_' . $row['id'], 'parisan_booking_nonce' ); ?>
									<button type="submit" class="button <?php echo 'cancelled' === $target_status ? 'button-link-delete' : 'button-secondary'; ?>" onclick="<?php echo esc_attr( $confirm_action ); ?>"><?php echo esc_html( $action_label ); ?></button>
								</form>
							<?php endforeach; ?>
							<?php if ( ! $actions ) : ?><span aria-label="<?php esc_attr_e( 'عملیات دیگری در دسترس نیست', 'parisan-core' ); ?>">—</span><?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav"><div class="tablenav-pages">
					<?php
					$base_url = add_query_arg( array( 'post_type' => 'parisan_staff', 'page' => 'parisan-bookings', 'status' => $status_filter, 'booking_date' => $booking_date_filter, 'staff_id' => $staff_filter, 'resource_id' => $resource_filter, 'paged' => 999999999 ), admin_url( 'edit.php' ) );
					$base_url = str_replace( '999999999', '%#%', esc_url( $base_url ) );
					echo wp_kses_post( paginate_links( array( 'base' => $base_url, 'format' => '', 'current' => $paged, 'total' => $total_pages ) ) );
					?>
				</div></div>
			<?php endif; ?>
		<?php else : ?>
			<p><?php esc_html_e( 'برای این فیلتر هنوز نوبتی ثبت نشده است.', 'parisan-core' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}
