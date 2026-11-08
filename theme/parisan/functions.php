<?php
/**
 * Parisan Beauty theme bootstrap.
 *
 * @package Parisan
 */

defined( 'ABSPATH' ) || exit;

define( 'PARISAN_THEME_VERSION', '0.3.0' );
define( 'PARISAN_THEME_DIR', get_template_directory() );
define( 'PARISAN_THEME_URI', get_template_directory_uri() );

/**
 * Configure theme supports and navigation.
 *
 * @return void
 */
function parisan_theme_setup() {
	load_theme_textdomain( 'parisan', PARISAN_THEME_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' )
	);
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 96,
			'width'       => 280,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	register_nav_menus(
		array(
			'primary' => esc_html__( 'منوی اصلی', 'parisan' ),
			'footer'  => esc_html__( 'منوی پابرگ', 'parisan' ),
		)
	);
}
add_action( 'after_setup_theme', 'parisan_theme_setup' );

/**
 * Register homepage art customization.
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager.
 * @return void
 */
function parisan_customize_register( $wp_customize ) {
	$wp_customize->add_section(
		'parisan_homepage',
		array(
			'title'       => esc_html__( 'پاریسان — صفحه اصلی', 'parisan' ),
			'description' => esc_html__( 'تصویر اصلی صفحه نخست را انتخاب کنید. در صورت خالی‌بودن، طرح هنری پیش‌فرض نمایش داده می‌شود.', 'parisan' ),
			'priority'    => 35,
		)
	);

	$wp_customize->add_setting(
		'parisan_hero_image',
		array(
			'default'           => '',
			'sanitize_callback' => 'esc_url_raw',
			'transport'         => 'refresh',
		)
	);

	$wp_customize->add_control(
		new WP_Customize_Image_Control(
			$wp_customize,
			'parisan_hero_image',
			array(
				'label'   => esc_html__( 'تصویر بخش اصلی صفحه نخست', 'parisan' ),
				'section' => 'parisan_homepage',
			)
		)
	);
}
add_action( 'customize_register', 'parisan_customize_register' );


/**
 * Accessible primary navigation fallback when no menu is assigned.
 *
 * @return void
 */
function parisan_primary_menu_fallback() {
	echo '<ul class="menu-list">';
	$items = array(
		array( home_url( '/' ), __( 'خانه', 'parisan' ) ),
		array( home_url( '/#services' ), __( 'خدمات', 'parisan' ) ),
		array( home_url( '/#team' ), __( 'متخصصان', 'parisan' ) ),
		array( home_url( '/#booking' ), __( 'رزرو نوبت', 'parisan' ) ),
	);
	foreach ( $items as $item ) {
		printf( '<li><a href="%1$s">%2$s</a></li>', esc_url( $item[0] ), esc_html( $item[1] ) );
	}
	echo '</ul>';
}


/**
 * Load only the theme's small, local stylesheet.
 *
 * @return void
 */
function parisan_enqueue_assets() {
	wp_enqueue_style(
		'parisan-main',
		PARISAN_THEME_URI . '/assets/css/main.css',
		array(),
		PARISAN_THEME_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'parisan_enqueue_assets' );

/**
 * Add a body class for the theme's RTL-aware design system.
 *
 * @param array $classes Existing body classes.
 * @return array
 */
function parisan_body_classes( $classes ) {
	$classes[] = is_rtl() ? 'parisan-is-rtl' : 'parisan-is-ltr';
	return $classes;
}
add_filter( 'body_class', 'parisan_body_classes' );
