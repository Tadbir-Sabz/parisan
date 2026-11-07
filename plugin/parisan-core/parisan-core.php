<?php
/**
 * Plugin Name: Parisan Core
 * Plugin URI: https://github.com/Tadbir-Sabz/parisan
 * Description: First-party salon operations foundation for the Parisan Beauty theme.
 * Version: 0.2.0
 * Author: Tadbir-Sabz
 * License: GPL-2.0-or-later
 * Text Domain: parisan-core
 *
 * @package ParisanCore
 */

defined( 'ABSPATH' ) || exit;

define( 'PARISAN_CORE_VERSION', '0.2.0' );
define( 'PARISAN_CORE_FILE', __FILE__ );
define( 'PARISAN_CORE_DIR', plugin_dir_path( __FILE__ ) );

require_once PARISAN_CORE_DIR . 'includes/staff.php';

/**
 * Register the plugin's initial content types.
 *
 * @return void
 */
function parisan_core_register_post_types() {
	$types = array(
		'parisan_service' => array(
			'singular' => __( 'خدمت سالن', 'parisan-core' ),
			'plural'   => __( 'خدمات سالن', 'parisan-core' ),
			'icon'     => 'dashicons-sparkles',
			'slug'     => 'services',
		),
		'parisan_staff' => array(
			'singular' => __( 'متخصص', 'parisan-core' ),
			'plural'   => __( 'متخصصان', 'parisan-core' ),
			'icon'     => 'dashicons-businesswoman',
			'slug'     => 'specialists',
		),
	);

	foreach ( $types as $type => $labels ) {
		register_post_type(
			$type,
			array(
				'labels' => array(
					'name'               => $labels['plural'],
					'singular_name'      => $labels['singular'],
					'add_new_item'       => sprintf( __( 'افزودن %s', 'parisan-core' ), $labels['singular'] ),
					'edit_item'          => sprintf( __( 'ویرایش %s', 'parisan-core' ), $labels['singular'] ),
					'view_item'          => sprintf( __( 'مشاهده %s', 'parisan-core' ), $labels['singular'] ),
					'search_items'       => sprintf( __( 'جستجوی %s', 'parisan-core' ), $labels['plural'] ),
					'not_found'          => __( 'موردی پیدا نشد.', 'parisan-core' ),
					'not_found_in_trash' => __( 'موردی در زباله‌دان نیست.', 'parisan-core' ),
					'menu_name'          => $labels['plural'],
				),
				'public'             => true,
				'publicly_queryable' => true,
				'show_in_rest'       => true,
				'has_archive'        => true,
				'rewrite'            => array( 'slug' => $labels['slug'] ),
				'menu_icon'          => $labels['icon'],
				'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'custom-fields' ),
			)
		);
	}
}
add_action( 'init', 'parisan_core_register_post_types' );

/**
 * Add service pricing and duration fields.
 *
 * @return void
 */
function parisan_core_add_service_meta_box() {
	add_meta_box(
		'parisan-service-details',
		__( 'اطلاعات خدمت', 'parisan-core' ),
		'parisan_core_render_service_meta_box',
		'parisan_service',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes_parisan_service', 'parisan_core_add_service_meta_box' );

/**
 * Render service metadata fields.
 *
 * @param WP_Post $post Current post.
 * @return void
 */
function parisan_core_render_service_meta_box( $post ) {
	wp_nonce_field( 'parisan_save_service_details', 'parisan_service_nonce' );
	$duration   = absint( get_post_meta( $post->ID, '_parisan_duration', true ) );
	$price      = absint( get_post_meta( $post->ID, '_parisan_price', true ) );
	$price_type = get_post_meta( $post->ID, '_parisan_price_type', true );
	$price_type = in_array( $price_type, array( 'fixed', 'from' ), true ) ? $price_type : 'fixed';
	?>
	<p>
		<label for="parisan-duration"><strong><?php esc_html_e( 'مدت زمان (دقیقه)', 'parisan-core' ); ?></strong></label><br>
		<input type="number" min="1" max="1440" step="1" id="parisan-duration" name="parisan_duration" value="<?php echo esc_attr( $duration ? $duration : '' ); ?>" class="small-text">
	</p>
	<p>
		<label for="parisan-price"><strong><?php esc_html_e( 'قیمت (تومان)', 'parisan-core' ); ?></strong></label><br>
		<input type="number" min="0" max="999999999" step="1" id="parisan-price" name="parisan_price" value="<?php echo esc_attr( $price ? $price : '' ); ?>" class="regular-text">
	</p>
	<p>
		<label for="parisan-price-type"><strong><?php esc_html_e( 'نوع نمایش قیمت', 'parisan-core' ); ?></strong></label><br>
		<select id="parisan-price-type" name="parisan_price_type">
			<option value="fixed" <?php selected( $price_type, 'fixed' ); ?>><?php esc_html_e( 'قیمت ثابت', 'parisan-core' ); ?></option>
			<option value="from" <?php selected( $price_type, 'from' ); ?>><?php esc_html_e( 'شروع قیمت از', 'parisan-core' ); ?></option>
		</select>
	</p>
	<?php
}

/**
 * Persist service metadata after validating permissions and nonce.
 *
 * @param int $post_id Current post ID.
 * @return void
 */
function parisan_core_save_service_meta( $post_id ) {
	if ( ! isset( $_POST['parisan_service_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['parisan_service_nonce'] ) ), 'parisan_save_service_details' ) ) {
		return;
	}

	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$duration = isset( $_POST['parisan_duration'] ) ? absint( wp_unslash( $_POST['parisan_duration'] ) ) : 0;
	$duration = min( 1440, $duration );
	$price    = isset( $_POST['parisan_price'] ) ? absint( wp_unslash( $_POST['parisan_price'] ) ) : 0;
	$price    = min( 999999999, $price );
	$type     = isset( $_POST['parisan_price_type'] ) ? sanitize_key( wp_unslash( $_POST['parisan_price_type'] ) ) : 'fixed';
	$type     = in_array( $type, array( 'fixed', 'from' ), true ) ? $type : 'fixed';

	update_post_meta( $post_id, '_parisan_duration', $duration );
	update_post_meta( $post_id, '_parisan_price', $price );
	update_post_meta( $post_id, '_parisan_price_type', $type );
}
add_action( 'save_post_parisan_service', 'parisan_core_save_service_meta' );

/**
 * Load translations when the plugin is initialized.
 *
 * @return void
 */
function parisan_core_load_textdomain() {
	load_plugin_textdomain( 'parisan-core', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'parisan_core_load_textdomain', 1 );
