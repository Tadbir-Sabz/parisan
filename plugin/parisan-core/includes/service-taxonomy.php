<?php
/**
 * Service catalogue taxonomy registration.
 *
 * @package ParisanCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register a hierarchical service category taxonomy.
 *
 * @return void
 */
function parisan_core_register_service_taxonomy() {
	register_taxonomy(
		'parisan_service_category',
		array( 'parisan_service' ),
		array(
			'labels' => array(
				'name'          => __( 'دسته‌بندی خدمات', 'parisan-core' ),
				'singular_name' => __( 'دسته‌بندی خدمت', 'parisan-core' ),
				'search_items'  => __( 'جستجوی دسته‌ها', 'parisan-core' ),
				'all_items'     => __( 'همه دسته‌ها', 'parisan-core' ),
				'edit_item'     => __( 'ویرایش دسته', 'parisan-core' ),
				'add_new_item'  => __( 'افزودن دسته جدید', 'parisan-core' ),
				'menu_name'     => __( 'دسته‌بندی خدمات', 'parisan-core' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'service-category' ),
		)
	);
}
add_action( 'init', 'parisan_core_register_service_taxonomy' );
