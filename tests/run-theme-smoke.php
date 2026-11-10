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
parisan_theme_smoke_assert( post_type_exists( 'parisan_faq' ), 'first-party FAQ content type is registered' );
parisan_theme_smoke_assert( function_exists( 'parisan_customize_brand_register' ), 'brand and identity customizer is registered' );
parisan_theme_smoke_assert( function_exists( 'parisan_output_brand_tokens' ), 'custom brand design tokens are output' );
parisan_theme_smoke_assert( function_exists( 'parisan_public_setting' ), 'public salon settings helper is registered' );
parisan_theme_smoke_assert( function_exists( 'parisan_home_content' ), 'owner-editable homepage content helper is registered' );
parisan_theme_smoke_assert( function_exists( 'parisan_customize_homepage_content_register' ), 'homepage copy controls are registered in the Customizer' );
parisan_theme_smoke_assert( function_exists( 'parisan_blog_index_url' ), 'blog index URL resolver is registered' );
$original_posts_page = get_option( 'page_for_posts' );
$original_show_on_front = get_option( 'show_on_front', 'posts' );
update_option( 'page_for_posts', 0 );
update_option( 'show_on_front', 'posts' );
parisan_theme_smoke_assert( home_url( '/' ) === parisan_blog_index_url(), 'blog fallback uses the home URL when latest posts are the front page' );
update_option( 'show_on_front', 'page' );
parisan_theme_smoke_assert( '' === parisan_blog_index_url(), 'blog resolver does not link to a static front page when no posts page is configured' );
ob_start();
parisan_primary_menu_fallback();
$static_front_navigation = ob_get_clean();
parisan_theme_smoke_assert( false === strpos( $static_front_navigation, 'مجله' ), 'fallback navigation omits the blog link when WordPress has no posts index' );
$posts_page_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Synthetic Posts Index' ), true );
parisan_theme_smoke_assert( ! is_wp_error( $posts_page_id ), 'synthetic posts index page is created' );
update_option( 'page_for_posts', $posts_page_id );
parisan_theme_smoke_assert( get_permalink( $posts_page_id ) === parisan_blog_index_url(), 'blog resolver uses the configured WordPress posts page' );
update_option( 'page_for_posts', $original_posts_page );
update_option( 'show_on_front', $original_show_on_front );
set_theme_mod( 'parisan_home_hero_title', 'عنوان اختصاصی سالن آزمایشی' );
parisan_theme_smoke_assert( 'عنوان اختصاصی سالن آزمایشی' === parisan_home_content( 'hero_title', 'fallback' ), 'homepage content setting returns the owner-selected value' );
remove_theme_mod( 'parisan_home_hero_title' );

parisan_theme_smoke_assert( function_exists( 'parisan_safe_theme_color' ), 'custom brand colors are validated before CSS output' );
set_theme_mod( 'parisan_brand_accent', 'red;}</style><script>alert(1)</script>' );
parisan_theme_smoke_assert( '#6f4657' === parisan_safe_theme_color( 'parisan_brand_accent', '#6f4657' ), 'unsafe brand color falls back to the safe default' );
remove_theme_mod( 'parisan_brand_accent' );
parisan_theme_smoke_assert( is_string( get_post_type_archive_link( 'parisan_portfolio' ) ) && '' !== get_post_type_archive_link( 'parisan_portfolio' ), 'portfolio archive URL is available' );
parisan_theme_smoke_assert( function_exists( 'parisan_primary_menu_fallback' ), 'primary navigation fallback is registered' );
parisan_theme_smoke_assert( function_exists( 'parisan_footer_menu_fallback' ), 'footer navigation fallback is registered' );
$header_source = file_get_contents( get_template_directory() . '/header.php' );
parisan_theme_smoke_assert( false !== strpos( $header_source, 'parisan-mobile-menu-toggle' ) && false !== strpos( $header_source, 'aria-controls="primary-navigation"' ), 'header renders a labelled mobile navigation control with an explicit target' );
$theme_script = file_get_contents( get_template_directory() . '/assets/js/theme.js' );
parisan_theme_smoke_assert( false !== strpos( $theme_script, "event.key === 'Escape'" ) && false !== strpos( $theme_script, "setAttribute('aria-expanded'" ), 'mobile navigation supports Escape and expanded state' );

foreach ( array( 'home.php', 'single.php', 'page-about.php', 'page-contact.php', 'page-faq.php', 'page-privacy-policy.php', 'page-terms.php' ) as $template_file ) {
	parisan_theme_smoke_assert( file_exists( get_template_directory() . '/' . $template_file ), 'required editorial template exists: ' . $template_file );
}
ob_start();
parisan_primary_menu_fallback();
$navigation = ob_get_clean();
parisan_theme_smoke_assert( false !== strpos( $navigation, home_url( '/#services' ) ), 'fallback navigation links to the services section' );
parisan_theme_smoke_assert( false !== strpos( $navigation, home_url( '/#team' ) ), 'fallback navigation links to the team section' );
parisan_theme_smoke_assert( false !== strpos( $navigation, home_url( '/#portfolio' ) ), 'fallback navigation links to the portfolio section' );
parisan_theme_smoke_assert( false !== strpos( $navigation, home_url( '/#booking' ) ), 'fallback navigation links to booking from every page' );
parisan_theme_smoke_assert( false !== strpos( $navigation, parisan_blog_index_url() ), 'fallback navigation links to the resolved blog index' );

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
parisan_theme_smoke_assert( function_exists( 'parisan_sanitize_hero_position' ), 'hero focal-point sanitizer is registered' );
parisan_theme_smoke_assert( false !== strpos( file_get_contents( get_template_directory() . '/functions.php' ), 'parisan_hero_image_alt' ), 'hero image alternative-text control is registered' );
parisan_theme_smoke_assert( 0 === parisan_sanitize_hero_position( -20 ) && 100 === parisan_sanitize_hero_position( 140 ) && 37 === parisan_sanitize_hero_position( '37' ), 'hero focal-point values are clamped to 0–100' );
set_theme_mod( 'parisan_hero_position_x', 63 );
set_theme_mod( 'parisan_hero_position_y', 28 );
set_theme_mod( 'parisan_hero_image_alt', 'تصویر فضای داخلی استودیوی پاریسان' );
set_theme_mod( 'parisan_hero_image', 'https://example.test/parisan-hero.jpg' );
ob_start();
include get_template_directory() . '/front-page.php';
$homepage = ob_get_clean();
$wp_query = $original_wp_query;
$post = $original_post;
remove_theme_mod( 'parisan_hero_image' );
remove_theme_mod( 'parisan_hero_position_x' );
remove_theme_mod( 'parisan_hero_position_y' );
remove_theme_mod( 'parisan_hero_image_alt' );

parisan_theme_smoke_assert( false !== strpos( $homepage, 'زیباییِ شما، با امضای ظرافت' ), 'editorial Persian hero default renders' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'Synthetic Homepage Service' ), 'homepage renders real published service content' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'Synthetic Homepage Specialist' ), 'homepage renders real published specialist content' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'Synthetic Portfolio Work' ), 'homepage renders published portfolio work' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'id="portfolio"' ), 'homepage exposes the portfolio section' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'مجلهٔ پاریسان' ), 'homepage renders the Persian editorial journal label' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'تجربه‌ای متناسب با شما' ), 'homepage renders the Persian introduction eyebrow' );
parisan_theme_smoke_assert( false === strpos( $homepage, 'A MORE PERSONAL EXPERIENCE' ) && false === strpos( $homepage, 'THE PARISAN JOURNAL' ) && false === strpos( $homepage, 'MAKE TIME FOR YOURSELF' ), 'homepage default editorial labels contain no leftover English-only copy' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'id="booking"' ), 'homepage exposes the booking anchor' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'رزرو آنلاین در حال حاضر فعال نیست' ) && false !== strpos( $homepage, 'برای هماهنگی نوبت با سالن تماس بگیرید' ), 'disabled booking state explains how to contact the salon' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'hero-art-photo' ) && false !== strpos( $homepage, 'https://example.test/parisan-hero.jpg' ), 'legacy owner-selected hero image URL remains backward-compatible' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'object-position:63% 28%;' ), 'owner-selected hero focal point is applied to the image crop' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'alt="تصویر فضای داخلی استودیوی پاریسان"' ), 'owner-provided alternative text is rendered on the hero image' );
parisan_theme_smoke_assert( false === strpos( $homepage, 'hero-art" role="img"' ), 'selected photography is not redundantly exposed as a generic image role' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'parisan-theme-toggle' ), 'accessible light/dark theme control renders in the site header' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'parisan-mobile-booking' ), 'mobile booking shortcut renders in the site footer' );
parisan_theme_smoke_assert( wp_script_is( 'parisan-theme', 'enqueued' ), 'theme interaction script is enqueued' );


parisan_theme_smoke_assert( function_exists( 'parisan_core_public_booking_admin_notice' ), 'booking owner-readiness notice is registered' );
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
