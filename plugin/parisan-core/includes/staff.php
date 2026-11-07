<?php
/**
 * Staff profiles and recurring weekly availability.
 *
 * @package ParisanCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add staff profile and weekly availability fields.
 *
 * @return void
 */
function parisan_core_add_staff_meta_box() {
	add_meta_box(
		'parisan-staff-details',
		__( 'اطلاعات متخصص و برنامه هفتگی', 'parisan-core' ),
		'parisan_core_render_staff_meta_box',
		'parisan_staff',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes_parisan_staff', 'parisan_core_add_staff_meta_box' );

/**
 * Render staff profile and weekly schedule fields.
 *
 * @param WP_Post $post Current post.
 * @return void
 */
function parisan_core_render_staff_meta_box( $post ) {
	wp_nonce_field( 'parisan_save_staff_details', 'parisan_staff_nonce' );
	$role     = get_post_meta( $post->ID, '_parisan_staff_role', true );
	$services = get_post_meta( $post->ID, '_parisan_staff_services', true );
	$schedule = get_post_meta( $post->ID, '_parisan_staff_weekly_schedule', true );
	$services = is_array( $services ) ? array_map( 'absint', $services ) : array();
	$schedule = is_array( $schedule ) ? $schedule : array();
	$days     = array(
		0 => __( 'یکشنبه', 'parisan-core' ),
		1 => __( 'دوشنبه', 'parisan-core' ),
		2 => __( 'سه‌شنبه', 'parisan-core' ),
		3 => __( 'چهارشنبه', 'parisan-core' ),
		4 => __( 'پنجشنبه', 'parisan-core' ),
		5 => __( 'جمعه', 'parisan-core' ),
		6 => __( 'شنبه', 'parisan-core' ),
	);
	$service_posts = get_posts(
		array(
			'post_type'      => 'parisan_service',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
	?>
	<p>
		<label for="parisan-staff-role"><strong><?php esc_html_e( 'عنوان تخصص', 'parisan-core' ); ?></strong></label><br>
		<input type="text" id="parisan-staff-role" name="parisan_staff_role" value="<?php echo esc_attr( $role ); ?>" class="regular-text" maxlength="120">
	</p>
	<p><strong><?php esc_html_e( 'خدمات قابل ارائه توسط این متخصص', 'parisan-core' ); ?></strong></p>
	<div class="parisan-staff-services">
		<?php if ( $service_posts ) : ?>
			<?php foreach ( $service_posts as $service_post ) : ?>
				<label style="display:inline-block;min-width:180px;margin:0 0 8px 0;">
					<input type="checkbox" name="parisan_staff_services[]" value="<?php echo esc_attr( $service_post->ID ); ?>" <?php checked( in_array( $service_post->ID, $services, true ) ); ?>>
					<?php echo esc_html( get_the_title( $service_post ) ); ?>
				</label>
			<?php endforeach; ?>
		<?php else : ?>
			<em><?php esc_html_e( 'ابتدا خدمات سالن را ثبت کنید.', 'parisan-core' ); ?></em>
		<?php endif; ?>
	</div>
	<hr>
	<p><strong><?php esc_html_e( 'ساعات کاری هفتگی (بر اساس ساعت محلی سالن)', 'parisan-core' ); ?></strong></p>
	<table class="widefat striped">
		<thead><tr><th><?php esc_html_e( 'روز', 'parisan-core' ); ?></th><th><?php esc_html_e( 'شروع', 'parisan-core' ); ?></th><th><?php esc_html_e( 'پایان', 'parisan-core' ); ?></th><th><?php esc_html_e( 'تعطیل', 'parisan-core' ); ?></th></tr></thead>
		<tbody>
		<?php foreach ( $days as $day => $label ) : ?>
			<?php
			$row   = isset( $schedule[ $day ] ) && is_array( $schedule[ $day ] ) ? $schedule[ $day ] : array();
			$off   = ! empty( $row['off'] );
			$start = isset( $row['start'] ) ? $row['start'] : '09:00';
			$end   = isset( $row['end'] ) ? $row['end'] : '18:00';
			?>
			<tr>
				<td><?php echo esc_html( $label ); ?></td>
				<td><input type="time" name="parisan_staff_schedule[<?php echo esc_attr( $day ); ?>][start]" value="<?php echo esc_attr( $start ); ?>"></td>
				<td><input type="time" name="parisan_staff_schedule[<?php echo esc_attr( $day ); ?>][end]" value="<?php echo esc_attr( $end ); ?>"></td>
				<td><label><input type="checkbox" name="parisan_staff_schedule[<?php echo esc_attr( $day ); ?>][off]" value="1" <?php checked( $off ); ?>> <?php esc_html_e( 'تعطیل', 'parisan-core' ); ?></label></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<p class="description"><?php esc_html_e( 'این برنامه، الگوی هفتگی است. مرخصی، تعطیلات خاص و استثناهای تاریخ‌دار در مرحله بعدی به مدل زمان‌بندی اضافه می‌شوند.', 'parisan-core' ); ?></p>
	<?php
}

/**
 * Persist staff metadata with strict validation.
 *
 * @param int $post_id Current post ID.
 * @return void
 */
function parisan_core_save_staff_meta( $post_id ) {
	if ( ! isset( $_POST['parisan_staff_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['parisan_staff_nonce'] ) ), 'parisan_save_staff_details' ) ) {
		return;
	}

	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$role = isset( $_POST['parisan_staff_role'] ) ? sanitize_text_field( wp_unslash( $_POST['parisan_staff_role'] ) ) : '';
	update_post_meta( $post_id, '_parisan_staff_role', function_exists( 'mb_substr' ) ? mb_substr( $role, 0, 120 ) : substr( $role, 0, 120 ) );

	$selected_services = isset( $_POST['parisan_staff_services'] ) && is_array( $_POST['parisan_staff_services'] ) ? array_map( 'absint', wp_unslash( $_POST['parisan_staff_services'] ) ) : array();
	$valid_services    = array();
	foreach ( array_unique( $selected_services ) as $service_id ) {
		if ( 'parisan_service' === get_post_type( $service_id ) ) {
			$valid_services[] = $service_id;
		}
	}
	update_post_meta( $post_id, '_parisan_staff_services', $valid_services );

	$input_schedule = isset( $_POST['parisan_staff_schedule'] ) && is_array( $_POST['parisan_staff_schedule'] ) ? wp_unslash( $_POST['parisan_staff_schedule'] ) : array();
	$clean_schedule = array();
	for ( $day = 0; $day <= 6; $day++ ) {
		$row   = isset( $input_schedule[ $day ] ) && is_array( $input_schedule[ $day ] ) ? $input_schedule[ $day ] : array();
		$off   = ! empty( $row['off'] );
		$start = isset( $row['start'] ) ? sanitize_text_field( $row['start'] ) : '';
		$end   = isset( $row['end'] ) ? sanitize_text_field( $row['end'] ) : '';
		$valid_time = static function ( $time ) {
			return is_string( $time ) && 1 === preg_match( '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $time );
		};

		if ( $off ) {
			$clean_schedule[ $day ] = array( 'off' => true );
		} elseif ( $valid_time( $start ) && $valid_time( $end ) && $end > $start ) {
			$clean_schedule[ $day ] = array(
				'off'   => false,
				'start' => $start,
				'end'   => $end,
			);
		} else {
			$clean_schedule[ $day ] = array( 'off' => true );
		}
	}
	update_post_meta( $post_id, '_parisan_staff_weekly_schedule', $clean_schedule );
}
add_action( 'save_post_parisan_staff', 'parisan_core_save_staff_meta' );
