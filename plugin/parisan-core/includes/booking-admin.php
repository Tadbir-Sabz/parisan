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
	$result = parisan_core_transition_booking( $booking_id, $to_status );
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
	wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'edit.php?post_type=parisan_staff' ) ) );
	exit;
}
add_action( 'admin_post_parisan_transition_booking', 'parisan_core_handle_booking_admin_transition' );

/**
 * Render the paginated booking operations list.
 *
 * @return void
 */
function parisan_core_render_booking_admin_page() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'اجازه مشاهده این صفحه را ندارید.', 'parisan-core' ), 403 );
	}

	global $wpdb;
	$table = $wpdb->prefix . 'parisan_bookings';
	$valid_statuses = array( 'pending', 'confirmed', 'cancelled', 'expired', 'completed', 'no_show' );
	$status_filter = isset( $_GET['status'] ) && is_string( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
	if ( ! in_array( $status_filter, $valid_statuses, true ) ) {
		$status_filter = '';
	}
	$paged = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
	$per_page = 30;
	$offset = ( $paged - 1 ) * $per_page;
	$where = '';
	$count_args = array();
	if ( '' !== $status_filter ) {
		$where = ' WHERE b.status = %s';
		$count_args[] = $status_filter;
	}
	$count_sql = "SELECT COUNT(*) FROM {$table} b{$where}";
	$total = (int) $wpdb->get_var( $count_args ? $wpdb->prepare( $count_sql, $count_args ) : $count_sql );
	$total_pages = max( 1, (int) ceil( $total / $per_page ) );

	$sql = "SELECT b.id, b.service_id, b.staff_id, b.customer_name, b.customer_phone, b.starts_at_utc, b.ends_at_utc, b.status, b.price_amount, b.currency_code, service_post.post_title AS service_title, staff_post.post_title AS staff_title
		FROM {$table} b
		LEFT JOIN {$wpdb->posts} service_post ON service_post.ID = b.service_id
		LEFT JOIN {$wpdb->posts} staff_post ON staff_post.ID = b.staff_id{$where}
		ORDER BY b.starts_at_utc DESC
		LIMIT %d OFFSET %d";
	$query_args = $count_args;
	$query_args[] = $per_page;
	$query_args[] = $offset;
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $query_args ), ARRAY_A );

	$status_labels = array(
		'pending' => __( 'در انتظار تأیید', 'parisan-core' ),
		'confirmed' => __( 'تأییدشده', 'parisan-core' ),
		'cancelled' => __( 'لغوشده', 'parisan-core' ),
		'expired' => __( 'منقضی‌شده', 'parisan-core' ),
		'completed' => __( 'انجام‌شده', 'parisan-core' ),
		'no_show' => __( 'عدم مراجعه', 'parisan-core' ),
	);
	$error_messages = array(
		'parisan_booking_not_found' => __( 'رزرو موردنظر پیدا نشد.', 'parisan-core' ),
		'parisan_booking_transition_forbidden' => __( 'اجازه تغییر وضعیت این رزرو را ندارید.', 'parisan-core' ),
		'parisan_invalid_booking_transition' => __( 'این تغییر وضعیت در چرخه رزرو مجاز نیست.', 'parisan-core' ),
		'parisan_booking_confirmation_conflict' => __( 'رزرو با نوبت یا عدم دسترسی دیگری تداخل دارد.', 'parisan-core' ),
		'parisan_booking_lock_unavailable' => __( 'تقویم متخصص در حال پردازش است؛ دوباره تلاش کنید.', 'parisan-core' ),
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'مدیریت نوبت‌ها', 'parisan-core' ); ?></h1>
		<p><?php esc_html_e( 'نوبت‌های ثبت‌شده را بررسی کنید و وضعیت آن‌ها را از مسیر مجاز چرخه رزرو تغییر دهید. زمان‌ها بر اساس منطقه زمانی تنظیم‌شده در وردپرس نمایش داده می‌شوند.', 'parisan-core' ); ?></p>
		<?php if ( isset( $_GET['booking_updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'وضعیت نوبت به‌روزرسانی شد.', 'parisan-core' ); ?></p></div>
		<?php elseif ( isset( $_GET['booking_error'] ) ) : ?>
			<?php $error_code = sanitize_key( wp_unslash( $_GET['booking_error'] ) ); ?>
			<div class="notice notice-error"><p><?php echo esc_html( isset( $error_messages[ $error_code ] ) ? $error_messages[ $error_code ] : __( 'تغییر وضعیت نوبت انجام نشد.', 'parisan-core' ) ); ?></p></div>
		<?php endif; ?>

		<form method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">
			<input type="hidden" name="post_type" value="parisan_staff">
			<input type="hidden" name="page" value="parisan-bookings">
			<label for="parisan-booking-status"><?php esc_html_e( 'وضعیت نوبت', 'parisan-core' ); ?></label>
			<select id="parisan-booking-status" name="status">
				<option value=""><?php esc_html_e( 'همه وضعیت‌ها', 'parisan-core' ); ?></option>
				<?php foreach ( $status_labels as $status => $label ) : ?>
					<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $status_filter, $status ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button button-secondary"><?php esc_html_e( 'فیلتر', 'parisan-core' ); ?></button>
		</form>

		<p><?php printf( esc_html__( 'تعداد نوبت‌ها: %d', 'parisan-core' ), $total ); ?></p>
		<?php if ( $rows ) : ?>
			<table class="widefat striped">
				<thead><tr>
					<th scope="col"><?php esc_html_e( 'زمان', 'parisan-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'مشتری', 'parisan-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'شماره تماس', 'parisan-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'خدمت', 'parisan-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'متخصص', 'parisan-core' ); ?></th>
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
						<td><?php echo esc_html( number_format_i18n( $price_toman ) . ' تومان' ); ?></td>
						<td><?php echo esc_html( isset( $status_labels[ $row['status'] ] ) ? $status_labels[ $row['status'] ] : $row['status'] ); ?></td>
						<td>
							<?php foreach ( $actions as $target_status => $action_label ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin:0 0 4px 4px">
									<input type="hidden" name="action" value="parisan_transition_booking">
									<input type="hidden" name="booking_id" value="<?php echo esc_attr( $row['id'] ); ?>">
									<input type="hidden" name="to_status" value="<?php echo esc_attr( $target_status ); ?>">
									<input type="hidden" name="booking_status_filter" value="<?php echo esc_attr( $status_filter ); ?>">
									<?php wp_nonce_field( 'parisan_transition_booking_' . $row['id'], 'parisan_booking_nonce' ); ?>
									<button type="submit" class="button <?php echo 'cancelled' === $target_status ? 'button-link-delete' : 'button-secondary'; ?>"><?php echo esc_html( $action_label ); ?></button>
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
					$base_url = add_query_arg( array( 'post_type' => 'parisan_staff', 'page' => 'parisan-bookings', 'status' => $status_filter, 'paged' => '%#%' ), admin_url( 'edit.php' ) );
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
