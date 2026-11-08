<?php
/**
 * Disposable WordPress smoke test for the Parisan public theme experience.
 *
 * @package Parisan
 */

defined( 'ABSPATH' ) || exit;

$_SERVER['SERVER_NAME'] = isset( $_SERVER['SERVER_NAME'] ) ? $_SERVER['SERVER_NAME'] : 'localhost';
$_SERVER['HTTP_HOST'] = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'localhost';
$_SERVER['REQUEST_URI'] = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';

function parisan_theme_smoke_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

parisan_theme_smoke_assert( 'parisan' === get_stylesheet(), 'Parisan theme is active' );
parisan_theme_smoke_assert( post_type_exists( 'parisan_portfolio' ), 'first-party portfolio content type is registered' );
parisan_theme_smoke_assert( taxonomy_exists( 'parisan_portfolio_category' ), 'portfolio category taxonomy is registered' );
parisan_theme_smoke_assert( is_string( get_post_type_archive_link( 'parisan_portfolio' ) ) && '' !== get_post_type_archive_link( 'parisan_portfolio' ), 'portfolio archive URL is available' );
parisan_theme_smoke_assert( function_exists( 'parisan_primary_menu_fallback' ), 'primary navigation fallback is registered' );
ob_start();
parisan_primary_menu_fallback();
$navigation = ob_get_clean();
parisan_theme_smoke_assert( false !== strpos( $navigation, home_url( '/#services' ) ), 'fallback navigation links to the services section' );
parisan_theme_smoke_assert( false !== strpos( $navigation, home_url( '/#team' ) ), 'fallback navigation links to the team section' );
parisan_theme_smoke_assert( false !== strpos( $navigation, home_url( '/#booking' ) ), 'fallback navigation links to booking from every page' );

$service_id = wp_insert_post(
	array(
		'post_type'   => 'parisan_service',
		'post_status' => 'publish',
		'post_title'  => 'Synthetic Homepage Service',
		'post_excerpt' => 'Synthetic service description for the homepage smoke test.',
	),
	true
);
parisan_theme_smoke_assert( ! is_wp_error( $service_id ), 'synthetic service is created' );
update_post_meta( $service_id, '_parisan_duration', 45 );
update_post_meta( $service_id, '_parisan_price', 250000 );
update_post_meta( $service_id, '_parisan_price_type', 'fixed' );

$portfolio_id = wp_insert_post(
	array(
		'post_type'   => 'parisan_portfolio',
		'post_status' => 'publish',
		'post_title'  => 'Synthetic Portfolio Work',
		'post_excerpt' => 'Synthetic portfolio work description.',
	),
	true
);
parisan_theme_smoke_assert( ! is_wp_error( $portfolio_id ), 'synthetic portfolio work is created' );

$staff_id = wp_insert_post(
	array(
		'post_type'   => 'parisan_staff',
		'post_status' => 'publish',
		'post_title'  => 'Synthetic Homepage Specialist',
		'post_excerpt' => 'Synthetic specialist profile for the homepage smoke test.',
	),
	true
);
parisan_theme_smoke_assert( ! is_wp_error( $staff_id ), 'synthetic specialist is created' );
update_post_meta( $staff_id, '_parisan_staff_role', 'متخصص آزمایشی' );

global $wp_query, $post;
$original_wp_query = $wp_query;
$original_post = $post;
$wp_query = new WP_Query(
	array(
		'post_type'      => 'page',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
	)
);
$post = null;

parisan_theme_smoke_assert( function_exists( 'parisan_customize_register' ), 'homepage image customization is registered' );
set_theme_mod( 'parisan_hero_image', 'https://example.test/parisan-hero.jpg' );
ob_start();
include get_template_directory() . '/front-page.php';
$homepage = ob_get_clean();
$wp_query = $original_wp_query;
$post = $original_post;
remove_theme_mod( 'parisan_hero_image' );

parisan_theme_smoke_assert( false !== strpos( $homepage, 'زیباییِ شما، با امضای ظرافت' ), 'editorial Persian hero renders' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'Synthetic Homepage Service' ), 'homepage renders real published service content' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'Synthetic Homepage Specialist' ), 'homepage renders real published specialist content' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'Synthetic Portfolio Work' ), 'homepage renders published portfolio work' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'id="portfolio"' ), 'homepage exposes the portfolio section' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'id="booking"' ), 'homepage exposes the booking anchor' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'رزرو آنلاین در حال حاضر فعال نیست' ), 'disabled booking shortcode displays its safe disabled state' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'hero-art-photo' ) && false !== strpos( $homepage, 'https://example.test/parisan-hero.jpg' ), 'owner-selected hero image is rendered in the homepage art frame' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'parisan-theme-toggle' ), 'accessible light/dark theme control renders in the site header' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'parisan-mobile-booking' ), 'mobile booking shortcut renders in the site footer' );
parisan_theme_smoke_assert( wp_script_is( 'parisan-theme', 'enqueued' ), 'theme interaction script is enqueued' );


parisan_theme_smoke_assert( function_exists( 'parisan_core_seo_settings' ), 'first-party SEO settings module is loaded' );
parisan_theme_smoke_assert( function_exists( 'parisan_core_output_seo_metadata' ), 'SEO metadata renderer is loaded' );
parisan_theme_smoke_assert( function_exists( 'parisan_core_request_is_private_or_booking' ), 'privacy-aware cache guard is loaded' );
parisan_theme_smoke_assert( false !== has_action( 'add_meta_boxes', 'parisan_core_add_seo_meta_boxes' ), 'per-content SEO editor fields are registered' );
parisan_theme_smoke_assert( false !== has_action( 'admin_menu', 'parisan_core_add_seo_admin_menu' ), 'SEO settings page is registered on the correct admin hook' );
parisan_theme_smoke_assert( false === has_action( 'wp_head', 'rel_canonical' ), 'WordPress core canonical output is disabled to prevent duplicate canonical tags' );
$seo_settings = parisan_core_sanitize_seo_settings( array(
	'default_description' => '<script>alert(1)</script> توضیح آزمایشی',
	'organization_name' => '<b>سالن آزمایشی</b>',
	'organization_url' => 'javascript:alert(1)',
	'output_json_ld' => '1',
) );
parisan_theme_smoke_assert( false === strpos( $seo_settings['default_description'], '<script' ) && false === strpos( $seo_settings['organization_name'], '<b>' ), 'SEO settings sanitize untrusted text' );
parisan_theme_smoke_assert( '' === $seo_settings['organization_url'], 'SEO settings reject unsafe organization URLs' );
$_GET['customer_token'] = 'synthetic-token';
parisan_theme_smoke_assert( parisan_core_request_is_private_or_booking(), 'customer-token request is marked non-cacheable' );
unset( $_GET['customer_token'] );
$_GET['rest_route'] = '/parisan/v1/availability';
parisan_theme_smoke_assert( parisan_core_request_is_private_or_booking(), 'query-style Parisan REST customer API request is marked non-cacheable' );
unset( $_GET['rest_route'] );
parisan_theme_smoke_assert( false === parisan_core_seo_plugin_controls_output(), 'first-party SEO output is available when no SEO plugin owns metadata' );
update_post_meta( $service_id, '_parisan_seo_title', 'عنوان سئوی آزمایشی خدمت' );
update_post_meta( $service_id, '_parisan_seo_description', 'توضیحات اختصاصی آزمایشی برای صفحه خدمت.' );
update_post_meta( $service_id, '_parisan_seo_noindex', '1' );
$seo_original_query = $wp_query;
$seo_original_post = $post;
$wp_query = new WP_Query( array( 'p' => $service_id, 'post_type' => 'parisan_service' ) );
$post = $wp_query->have_posts() ? $wp_query->posts[0] : null;
if ( $post ) {
	setup_postdata( $post );
}
$robots = apply_filters( 'wp_robots', array( 'index' => true, 'follow' => true ) );
parisan_theme_smoke_assert( ! empty( $robots['noindex'] ) && empty( $robots['index'] ), 'per-content noindex directive is emitted when selected' );
ob_start();
parisan_core_output_seo_metadata();
$seo_head = ob_get_clean();
$wp_query = $seo_original_query;
$post = $seo_original_post;
wp_reset_postdata();
parisan_theme_smoke_assert( false !== strpos( $seo_head, 'name="description"' ) && false !== strpos( $seo_head, 'توضیحات اختصاصی آزمایشی برای صفحه خدمت.' ), 'per-page meta description is emitted' );
parisan_theme_smoke_assert( false !== strpos( $seo_head, 'rel="canonical"' ) && false !== strpos( $seo_head, 'og:title' ), 'canonical and Open Graph metadata are emitted' );
parisan_theme_smoke_assert( false !== strpos( $seo_head, 'application/ld+json' ) && false !== strpos( $seo_head, '"@type":"Service"' ), 'service JSON-LD is emitted for service detail pages' );

echo "PASS: Parisan theme, portfolio content, dynamic homepage, booking disabled state, SEO controls, sanitization, and private-cache guards.\\n";
