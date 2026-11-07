<?php
/**
 * Date-specific staff schedule exception administration.
 *
 * @package ParisanCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the calendar exception page below Specialists.
 *
 * @return void
 */
function parisan_core_register_schedule_exception_page() {
	add_submenu_page(
		'edit.php?post_type=parisan_staff',
		__( 'تقویم و تعطیلات', 'parisan-core' ),
		__( 'تقویم و تعطیلات', 'parisan-core' ),
		'edit_others_posts',
		'parisan-schedule-exceptions',
		'parisan_core_render_schedule_exception_page'
	);
}
add_action( 'admin_menu', 'parisan_core_register_schedule_exception_page' );

/**
 * Handle exception creation using an authenticated admin-post request.
 *
 * @return void
 */
function parisan_core_handle_schedule_exception_create() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'اجازه انجام این عملیات را ندارید.', 'parisan-core' ), 403 );
	}
	check_admin_referer( 'parisan_create_schedule_exception', 'parisan_exception_nonce' );

	$staff_id = isset( $_POST['staff_id'] ) && is_scalar( $_POST['staff_id'] ) ? absint( wp_unslash( $_POST['staff_id'] ) ) : 0;
	if ( ! $staff_id || 'parisan_staff' !== get_post_type( $staff_id ) || 'publish' !== get_post_status( $staff_id ) || ! current_user_can( 'edit_post', $staff_id ) ) {
		wp_die( esc_html__( 'متخصص انتخاب‌شده معتبر نیست یا دسترسی ویرایش آن را ندارید.', 'parisan-core' ), 403 );
	}

	$date = isset( $_POST['exception_date'] ) && is_string( $_POST['exception_date'] ) ? sanitize_text_field( wp_unslash( $_POST['exception_date'] ) ) : '';
	$all_day = isset( $_POST['all_day'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['all_day'] ) );
	$start_time = $all_day ? '00:00' : ( isset( $_POST['start_time'] ) && is_string( $_POST['start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['start_time'] ) ) : '' );
	$end_time = $all_day ? '00:00' : ( isset( $_POST['end_time'] ) && is_string( $_POST['end_time'] ) ? sanitize_text_field( wp_unslash( $_POST['end_time'] ) ) : '' );

	$timezone = wp_timezone();
	$local_date = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, $timezone );
	$date_errors = DateTimeImmutable::getLastErrors();
	if ( false === $local_date || ( is_array( $date_errors ) && ( $date_errors['warning_count'] > 0 || $date_errors['error_count'] > 0 ) ) || $local_date->format( 'Y-m-d' ) !== $date ) {
		wp_die( esc_html__( 'تاریخ واردشده معتبر نیست.', 'parisan-core' ), 400 );
	}

	$end_date = $all_day ? $local_date->modify( '+1 day' )->format( 'Y-m-d' ) : $date;
	$interval = parisan_core_local_interval_to_utc( $date, $start_time, $end_date, $end_time, $timezone );
	if ( false === $interval ) {
		wp_die( esc_html__( 'بازه زمانی معتبر نیست. ساعت پایان باید بعد از شروع باشد و زمان محلی باید وجود داشته باشد.', 'parisan-core' ), 400 );
	}

	$note = isset( $_POST['note'] ) && is_string( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '';
	if ( function_exists( 'mb_substr' ) ) {
		$note = mb_substr( $note, 0, 255 );
	} else {
		$note = substr( $note, 0, 255 );
	}

	global $wpdb;
	$table = $wpdb->prefix . 'parisan_schedule_exceptions';
	$now = current_time( 'mysql', true );
	$inserted = $wpdb->insert(
		$table,
		array(
			'staff_id' => $staff_id,
			'starts_at_utc' => $interval['start']->format( 'Y-m-d H:i:s' ),
			'ends_at_utc' => $interval['end']->format( 'Y-m-d H:i:s' ),
			'exception_type' => 'unavailable',
			'note' => $note,
			'created_at_utc' => $now,
		),
		array( '%d', '%s', '%s', '%s', '%s', '%s' )
	);
	if ( false === $inserted ) {
		wp_die( esc_html__( 'ذخیره استثنای تقویم انجام نشد.', 'parisan-core' ), 500 );
	}

	$redirect = add_query_arg( array( 'page' => 'parisan-schedule-exceptions', 'exception_saved' => 1 ), admin_url( 'edit.php?post_type=parisan_staff' ) );
	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_post_parisan_create_schedule_exception', 'parisan_core_handle_schedule_exception_create' );

/**
 * Handle deletion of a single schedule exception.
 *
 * @return void
 */
function parisan_core_handle_schedule_exception_delete() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'اجازه انجام این عملیات را ندارید.', 'parisan-core' ), 403 );
	}
	$exception_id = isset( $_POST['exception_id'] ) && is_scalar( $_POST['exception_id'] ) ? absint( wp_unslash( $_POST['exception_id'] ) ) : 0;
	$staff_id = isset( $_POST['staff_id'] ) && is_scalar( $_POST['staff_id'] ) ? absint( wp_unslash( $_POST['staff_id'] ) ) : 0;
	check_admin_referer( 'parisan_delete_schedule_exception_' . $exception_id, 'parisan_exception_nonce' );

	if ( ! $exception_id || ! $staff_id || 'parisan_staff' !== get_post_type( $staff_id ) || ! current_user_can( 'edit_post', $staff_id ) ) {
		wp_die( esc_html__( 'استثنا یا متخصص معتبر نیست.', 'parisan-core' ), 403 );
	}

	global $wpdb;
	$table = $wpdb->prefix . 'parisan_schedule_exceptions';
	$deleted = $wpdb->delete( $table, array( 'id' => $exception_id, 'staff_id' => $staff_id ), array( '%d', '%d' ) );
	if ( false === $deleted ) {
		wp_die( esc_html__( 'حذف استثنا انجام نشد.', 'parisan-core' ), 500 );
	}

	$redirect = add_query_arg( array( 'page' => 'parisan-schedule-exceptions', 'exception_deleted' => 1 ), admin_url( 'edit.php?post_type=parisan_staff' ) );
	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_post_parisan_delete_schedule_exception', 'parisan_core_handle_schedule_exception_delete' );

/**
 * Render the date-specific calendar exception manager.
 *
 * @return void
 */
function parisan_core_render_schedule_exception_page() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'اجازه مشاهده این صفحه را ندارید.', 'parisan-core' ), 403 );
	}

	$staff = get_posts(
		array(
			'post_type' => 'parisan_staff',
			'post_status' => 'publish',
			'posts_per_page' => 200,
			'orderby' => 'title',
			'order' => 'ASC',
		)
	);
	$staff_ids = array();
	foreach ( $staff as $person ) {
		if ( current_user_can( 'edit_post', $person->ID ) ) {
			$staff_ids[] = (int) $person->ID;
		}
	}
	global $wpdb;
	$table = $wpdb->prefix . 'parisan_schedule_exceptions';
	$rows = array();
	if ( $staff_ids ) {
		$placeholders = implode( ',', array_fill( 0, count( $staff_ids ), '%d' ) );
		$sql = $wpdb->prepare( "SELECT id, staff_id, starts_at_utc, ends_at_utc, note FROM {$table} WHERE exception_type = 'unavailable' AND staff_id IN ({$placeholders}) ORDER BY starts_at_utc ASC LIMIT 200", $staff_ids );
		$rows = $wpdb->get_results( $sql, ARRAY_A );
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'تقویم و تعطیلات متخصصان', 'parisan-core' ); ?></h1>
		<p><?php esc_html_e( 'برای ثبت مرخصی، تعطیلی یک روز کامل یا مسدودکردن بخشی از ساعات کاری، از فرم زیر استفاده کنید. زمان‌ها بر اساس منطقه زمانی تنظیم‌شده در وردپرس ثبت می‌شوند.', 'parisan-core' ); ?></p>
		<?php if ( isset( $_GET['exception_saved'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'استثنای تقویم ذخیره شد.', 'parisan-core' ); ?></p></div>
		<?php elseif ( isset( $_GET['exception_deleted'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'استثنای تقویم حذف شد.', 'parisan-core' ); ?></p></div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'ثبت عدم دسترسی جدید', 'parisan-core' ); ?></h2>
		<?php if ( $staff_ids ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="parisan_create_schedule_exception">
				<?php wp_nonce_field( 'parisan_create_schedule_exception', 'parisan_exception_nonce' ); ?>
				<table class="form-table" role="presentation"><tbody>
					<tr><th scope="row"><label for="parisan-exception-staff"><?php esc_html_e( 'متخصص', 'parisan-core' ); ?></label></th><td><select required id="parisan-exception-staff" name="staff_id"><?php foreach ( $staff as $person ) : if ( ! in_array( (int) $person->ID, $staff_ids, true ) ) { continue; } ?><option value="<?php echo esc_attr( $person->ID ); ?>"><?php echo esc_html( get_the_title( $person ) ); ?></option><?php endforeach; ?></select></td></tr>
					<tr><th scope="row"><label for="parisan-exception-date"><?php esc_html_e( 'تاریخ', 'parisan-core' ); ?></label></th><td><input required type="date" id="parisan-exception-date" name="exception_date"></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'نوع بازه', 'parisan-core' ); ?></th><td><label><input type="checkbox" name="all_day" value="1"> <?php esc_html_e( 'تمام روز تعطیل / مرخصی', 'parisan-core' ); ?></label></td></tr>
					<tr><th scope="row"><label for="parisan-exception-start"><?php esc_html_e( 'از ساعت', 'parisan-core' ); ?></label></th><td><input required type="time" id="parisan-exception-start" name="start_time" value="09:00"></td></tr>
					<tr><th scope="row"><label for="parisan-exception-end"><?php esc_html_e( 'تا ساعت', 'parisan-core' ); ?></label></th><td><input required type="time" id="parisan-exception-end" name="end_time" value="18:00"></td></tr>
					<tr><th scope="row"><label for="parisan-exception-note"><?php esc_html_e( 'توضیح', 'parisan-core' ); ?></label></th><td><input type="text" class="regular-text" maxlength="255" id="parisan-exception-note" name="note" placeholder="<?php esc_attr_e( 'مثلاً مرخصی، تعطیلی رسمی یا جلسه', 'parisan-core' ); ?>"></td></tr>
				</tbody></table>
				<?php submit_button( __( 'ثبت در تقویم', 'parisan-core' ) ); ?>
			</form>
		<?php else : ?>
			<p><?php esc_html_e( 'متخصص منتشرشده و قابل ویرایشی وجود ندارد. ابتدا یک متخصص ثبت کنید.', 'parisan-core' ); ?></p>
		<?php endif; ?>

		<hr><h2><?php esc_html_e( 'استثناهای ثبت‌شده', 'parisan-core' ); ?></h2>
		<?php if ( $rows ) : ?>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'متخصص', 'parisan-core' ); ?></th><th><?php esc_html_e( 'شروع', 'parisan-core' ); ?></th><th><?php esc_html_e( 'پایان', 'parisan-core' ); ?></th><th><?php esc_html_e( 'توضیح', 'parisan-core' ); ?></th><th><?php esc_html_e( 'عملیات', 'parisan-core' ); ?></th></tr></thead><tbody>
			<?php foreach ( $rows as $row ) : ?>
				<?php $display_start = ( new DateTimeImmutable( $row['starts_at_utc'], new DateTimeZone( 'UTC' ) ) )->setTimezone( wp_timezone() ); $display_end = ( new DateTimeImmutable( $row['ends_at_utc'], new DateTimeZone( 'UTC' ) ) )->setTimezone( wp_timezone() ); ?>
				<tr><td><?php echo esc_html( get_the_title( (int) $row['staff_id'] ) ); ?></td><td><?php echo esc_html( wp_date( 'Y-m-d H:i', $display_start->getTimestamp(), wp_timezone() ) ); ?></td><td><?php echo esc_html( wp_date( 'Y-m-d H:i', $display_end->getTimestamp(), wp_timezone() ) ); ?></td><td><?php echo esc_html( $row['note'] ); ?></td><td><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="parisan_delete_schedule_exception"><input type="hidden" name="exception_id" value="<?php echo esc_attr( $row['id'] ); ?>"><input type="hidden" name="staff_id" value="<?php echo esc_attr( $row['staff_id'] ); ?>"><?php wp_nonce_field( 'parisan_delete_schedule_exception_' . $row['id'], 'parisan_exception_nonce' ); ?><?php submit_button( __( 'حذف', 'parisan-core' ), 'delete small', 'submit', false, array( 'onclick' => "return confirm('" . esc_js( __( 'از حذف این بازه مطمئن هستید؟', 'parisan-core' ) ) . "');" ) ); ?></form></td></tr>
			<?php endforeach; ?>
			</tbody></table>
		<?php else : ?>
			<p><?php esc_html_e( 'هنوز استثنایی ثبت نشده است.', 'parisan-core' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}
