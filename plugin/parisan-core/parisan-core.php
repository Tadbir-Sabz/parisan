<?php
/**
 * Plugin Name: Parisan Core
 * Plugin URI: https://github.com/Tadbir-Sabz/parisan
 * Description: First-party salon operations foundation for the Parisan Beauty theme.
 * Version: 0.1.0
 * Author: Tadbir-Sabz
 * License: GPL-2.0-or-later
 * Text Domain: parisan-core
 *
 * @package ParisanCore
 */

defined( 'ABSPATH' ) || exit;

define( 'PARISAN_CORE_VERSION', '0.1.0' );
define( 'PARISAN_CORE_FILE', __FILE__ );
define( 'PARISAN_CORE_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Register the plugin's initial content types.
 *
 * These are editorial catalog entities only. Transactional booking data
 * will be introduced through versioned schema migrations in a later slice.
 *
 * @return void
 */
function parisan_core_register_post_types() {
	$types = array(
		'parisan_service' => array(
			'singular' => __( 'خدمت سالن', 'parisan-core' ),
			'plural'   => __( 'خدمات سالن', 'parisan-core' ),
			'icon'     => 'dashicons-sparkles',
		),
		'parisan_staff'   => array(
			'singular' => __( 'متخصص', 'parisan-core' ),
			'plural'   => __( 'متخصصان', 'parisan-core' ),
			'icon'     => 'dashicons-businesswoman',
		),
	);

	foreach ( $types as $type => $labels ) {
		register_post_type(
			$type,
			array(
				'labels' => array(
					'name'                  => $labels['plural'],
					'singular_name'         => $labels['singular'],
					'add_new_item'          => sprintf( __( 'افزودن %s', 'parisan-core' ), $labels['singular'] ),
					'edit_item'             => sprintf( __( 'ویرایش %s', 'parisan-core' ), $labels['singular'] ),
					'view_item'             => sprintf( __( 'مشاهده %s', 'parisan-core' ), $labels['singular'] ),
					'search_items'          => sprintf( __( 'جستجوی %s', 'parisan-core' ), $labels['plural'] ),
					'not_found'             => __( 'موردی پیدا نشد.', 'parisan-core' ),
					'not_found_in_trash'    => __( 'موردی در زباله‌دان نیست.', 'parisan-core' ),
					'menu_name'             => $labels['plural'],
				),
				'public'             => true,
				'publicly_queryable' => true,
				'show_in_rest'       => true,
				'has_archive'        => true,
				'rewrite'            => array( 'slug' => $type === 'parisan_service' ? 'services' : 'specialists' ),
				'menu_icon'          => $labels['icon'],
				'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
			)
		);
	}
}
add_action( 'init', 'parisan_core_register_post_types' );

/**
 * Load translations when the plugin is initialized.
 *
 * @return void
 */
function parisan_core_load_textdomain() {
	load_plugin_textdomain( 'parisan-core', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'parisan_core_load_textdomain', 1 );
