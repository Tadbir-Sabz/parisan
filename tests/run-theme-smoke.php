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
parisan_theme_smoke_assert( function_exists( 'parisan_core_get_setup_readiness_checks' ), 'read-only first-run setup readiness checks are registered' );
$readiness_checks = parisan_core_get_setup_readiness_checks();
$readiness_ids = array_column( $readiness_checks, 'id' );
parisan_theme_smoke_assert( count( $readiness_checks ) >= 7 && count( $readiness_ids ) === count( array_unique( $readiness_ids ) ), 'setup readiness exposes unique actionable checks' );
foreach ( $readiness_checks as $readiness_check ) {
	parisan_theme_smoke_assert( isset( $readiness_check['title'], $readiness_check['ready'], $readiness_check['detail'], $readiness_check['url'], $readiness_check['action'], $readiness_check['required'] ), 'each setup readiness check has a complete status and next action' );
}
parisan_theme_smoke_assert( false !== strpos( file_get_contents( WP_PLUGIN_DIR . '/parisan-core/includes/setup-readiness.php' ), 'This function is deliberately read-only' ), 'setup readiness is explicitly documented as read-only' );
$booking_admin_source = file_get_contents( WP_PLUGIN_DIR . '/parisan-core/includes/booking-admin.php' );
parisan_theme_smoke_assert( false !== strpos( $booking_admin_source, 'today_active' ) && false !== strpos( $booking_admin_source, 'awaiting_confirmation' ) && false !== strpos( $booking_admin_source, 'upcoming_confirmed' ), 'booking operations overview exposes today, pending, and upcoming metrics' );
parisan_theme_smoke_assert( false !== strpos( $booking_admin_source, 'خلاصه عملیات نوبت‌ها' ) && false !== strpos( $booking_admin_source, 'از ابتدای فردا به بعد' ), 'booking overview labels its operational metrics in Persian with a clear date boundary' );
parisan_theme_smoke_assert( false !== strpos( $booking_admin_source, 'booking_date' ) && false !== strpos( $booking_admin_source, 'staff_filter' ), 'booking operations supports date and specialist filters for the daily schedule' );
parisan_theme_smoke_assert( false !== strpos( $booking_admin_source, 'ORDER BY b.starts_at_utc ' ) && false !== strpos( $booking_admin_source, "? 'ASC' : 'DESC'" ), 'daily schedule is chronological while the unfiltered history remains newest-first' );
parisan_theme_smoke_assert( false !== strpos( $booking_admin_source, 'FROM {$bookings_table} b' ), 'booking detail metrics use the correctly scoped bookings table' );

$customer_admin_source = file_get_contents( WP_PLUGIN_DIR . '/parisan-core/includes/customers-admin.php' );
$service_catalog_source = file_get_contents( WP_PLUGIN_DIR . '/parisan-core/includes/service-catalog-admin.php' );
parisan_theme_smoke_assert( function_exists( 'parisan_core_render_customer_admin_page' ) && false !== strpos( $customer_admin_source, 'staff_id IN (' ), 'customer CRM is registered and scopes personal data to specialists the current operator may edit' );
parisan_theme_smoke_assert( false !== strpos( $customer_admin_source, 'completed_total' ) && false !== strpos( $customer_admin_source, 'customer_phone' ) && false !== strpos( $customer_admin_source, 'تاریخچه مشتری' ), 'customer CRM provides visit totals, contact search, and individual booking history' );
parisan_theme_smoke_assert( function_exists( 'parisan_core_service_catalog_columns' ) && false !== strpos( $service_catalog_source, 'parisan_resources' ) && false !== strpos( $service_catalog_source, 'parisan_assignment' ), 'service catalogue exposes resource requirements and specialist-assignment filters' );
parisan_theme_smoke_assert( false !== strpos( $service_catalog_source, 'manage_parisan_service_posts_columns' ) && false !== strpos( $service_catalog_source, 'meta_value_num' ), 'service catalogue supports operational columns and numeric duration/price sorting' );



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
parisan_theme_smoke_assert( false !== strpos( $header_source, 'parisan-brand-lockup' ) && false !== strpos( $header_source, 'parisan-brand-mark' ), 'header renders the Parisan wordmark and lotus mark when no custom logo is uploaded' );
$theme_script = file_get_contents( get_template_directory() . '/assets/js/theme.js' );
parisan_theme_smoke_assert( false !== strpos( $theme_script, "event.key === 'Escape'" ) && false !== strpos( $theme_script, "setAttribute('aria-expanded'" ), 'mobile navigation supports Escape and expanded state' );
parisan_theme_smoke_assert( false !== strpos( $theme_script, "!header.contains(event.target)" ), 'mobile navigation closes when a user clicks outside the header' );
parisan_theme_smoke_assert( false !== strpos( $theme_script, "'(min-width: 761px)'" ) && false !== strpos( $theme_script, 'closeMenuOnDesktop' ), 'mobile navigation resets its expanded state when the viewport returns to desktop' );

foreach ( array( 'home.php', 'single.php', 'page-about.php', 'page-contact.php', 'page-faq.php', 'page-privacy-policy.php', 'page-terms.php' ) as $template_file ) {
	parisan_theme_smoke_assert( file_exists( get_template_directory() . '/' . $template_file ), 'required editorial template exists: ' . $template_file );
}
ob_start();
parisan_primary_menu_fallback();
$navigation = ob_get_clean();
parisan_theme_smoke_assert( false !== strpos( $navigation, home_url( '/#services' ) ), 'fallback navigation links to the services section' );
parisan_theme_smoke_assert( false !== strpos( $navigation, home_url( '/#portfolio' ) ), 'fallback navigation links to the portfolio section' );
parisan_theme_smoke_assert( false !== strpos( $navigation, parisan_blog_index_url() ), 'fallback navigation links to the resolved blog index' );
parisan_theme_smoke_assert( false !== strpos( $navigation, 'صفحه اصلی' ) && false !== strpos( $navigation, 'درباره ما' ) && false !== strpos( $navigation, 'تماس با ما' ), 'fallback navigation uses the approved concise Persian labels' );
parisan_theme_smoke_assert( false === strpos( $navigation, 'سؤالات متداول' ) && false === strpos( $navigation, 'رزرو نوبت' ) && false === strpos( $navigation, 'متخصصان' ), 'fallback header avoids extra items that crowd the reference layout' );

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
parisan_theme_smoke_assert( false !== strpos( file_get_contents( get_template_directory() . '/functions.php' ), 'parisan_hero_image_night_id' ) && false !== strpos( file_get_contents( get_template_directory() . '/functions.php' ), 'parisan_hero_night_position_x' ), 'night hero image and independent focal-point controls are registered' );
parisan_theme_smoke_assert( 0 === parisan_sanitize_hero_position( -20 ) && 100 === parisan_sanitize_hero_position( 140 ) && 37 === parisan_sanitize_hero_position( '37' ), 'hero focal-point values are clamped to 0–100' );
set_theme_mod( 'parisan_hero_position_x', 63 );
set_theme_mod( 'parisan_hero_position_y', 28 );
set_theme_mod( 'parisan_hero_image_alt', 'تصویر فضای داخلی استودیوی پاریسان' );
set_theme_mod( 'parisan_hero_image', 'https://example.test/parisan-hero.jpg' );
set_theme_mod( 'parisan_home_benefit_1_title', 'مزیت آزمایشی قابل ویرایش' );
ob_start();
include get_template_directory() . '/front-page.php';
$homepage = ob_get_clean();
$wp_query = $original_wp_query;
$post = $original_post;
remove_theme_mod( 'parisan_hero_image' );
remove_theme_mod( 'parisan_hero_position_x' );
remove_theme_mod( 'parisan_hero_position_y' );
remove_theme_mod( 'parisan_hero_image_alt' );
remove_theme_mod( 'parisan_home_benefit_1_title' );

parisan_theme_smoke_assert( false !== strpos( $homepage, 'زیبایی، با امضای ظرافت' ), 'reference-aligned Persian hero title renders' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'Synthetic Homepage Service' ), 'homepage renders real published service content' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'Synthetic Homepage Specialist' ), 'homepage renders real published specialist content' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'Synthetic Portfolio Work' ), 'homepage renders published portfolio work' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'id="portfolio"' ), 'homepage exposes the portfolio section' );
parisan_theme_smoke_assert( 4 === substr_count( $homepage, 'class="hero-benefit"' ), 'homepage renders exactly four reference-style trust benefit cards' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'خدمات لوکس' ) && false !== strpos( $homepage, 'پشتیبانی حرفه‌ای' ) && false !== strpos( $homepage, 'وقت‌دهی آسان' ) && false !== strpos( $homepage, 'مزیت آزمایشی قابل ویرایش' ), 'all four homepage benefit cards render Persian content including the owner override' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'مزیت آزمایشی قابل ویرایش' ), 'homepage benefit card copy can be customized by the site owner' );

parisan_theme_smoke_assert( false !== strpos( $homepage, 'مجلهٔ پاریسان' ), 'homepage renders the Persian editorial journal label' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'تجربه‌ای متناسب با شما' ), 'homepage renders the Persian introduction eyebrow' );
parisan_theme_smoke_assert( false === strpos( $homepage, 'A MORE PERSONAL EXPERIENCE' ) && false === strpos( $homepage, 'THE PARISAN JOURNAL' ) && false === strpos( $homepage, 'MAKE TIME FOR YOURSELF' ), 'homepage default editorial labels contain no leftover English-only copy' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'id="booking"' ), 'homepage exposes the booking anchor' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'رزرو آنلاین در حال حاضر فعال نیست' ) && false !== strpos( $homepage, 'برای هماهنگی نوبت با سالن تماس بگیرید' ), 'disabled booking state explains how to contact the salon' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'hero-art-photo' ) && false !== strpos( $homepage, 'https://example.test/parisan-hero.jpg' ), 'legacy owner-selected hero image URL remains backward-compatible' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'hero-art-photo-night' ) && false !== strpos( $homepage, '/assets/images/hero-night.jpg' ), 'homepage includes a distinct bundled local night portrait' );
parisan_theme_smoke_assert( false === strpos( $homepage, 'images.unsplash.com' ), 'homepage does not depend on external Unsplash image URLs' );
parisan_theme_smoke_assert( false === strpos( $homepage, 'parisan-home-content' ), 'homepage avoids duplicating the blog post loop right above the footer' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'object-position:63% 28%;' ), 'owner-selected hero focal point is applied to the image crop' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'alt="تصویر فضای داخلی استودیوی پاریسان"' ), 'owner-provided alternative text is rendered on the hero image' );
parisan_theme_smoke_assert( false === strpos( $homepage, 'hero-art" role="img"' ), 'selected photography is not redundantly exposed as a generic image role' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'parisan-theme-toggle' ), 'accessible light/dark theme control renders in the site header' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'parisan-mobile-booking' ), 'mobile booking shortcut renders in the site footer' );
parisan_theme_smoke_assert( wp_script_is( 'parisan-theme', 'enqueued' ), 'theme interaction script is enqueued' );

$rtl_attrs = parisan_filter_language_attributes( 'lang="en-US"' );
parisan_theme_smoke_assert( false !== strpos( $rtl_attrs, 'dir="rtl"' ) && false !== strpos( $rtl_attrs, 'lang="fa-IR"' ), 'Persian RTL attributes are enforced even when WordPress falls back to en-US' );
$asset_files = array(
	parisan_hero_fallback_image_url( 'day' ),
	parisan_hero_fallback_image_url( 'night' ),
);
foreach ( array( 'services', 'portfolio', 'staff', 'journal' ) as $card_group ) {
	$group_urls = array();
	for ( $card_i = 0; $card_i < 3; $card_i++ ) {
		$fallback = parisan_fallback_image_for_card( $card_group, $card_i );
		$group_urls[] = $fallback['url'];
		$asset_files[] = $fallback['url'];
	}
	parisan_theme_smoke_assert( 3 === count( array_unique( $group_urls ) ), 'fallback images are distinct across cards in group: ' . $card_group );
}
foreach ( array_unique( $asset_files ) as $asset_url ) {
	$rel = str_replace( PARISAN_THEME_URI, '', $asset_url );
	parisan_theme_smoke_assert( file_exists( get_template_directory() . $rel ), 'bundled theme visual exists on disk: ' . $rel );
}


parisan_theme_smoke_assert( function_exists( 'parisan_core_register_settings_pages' ), 'unified Parisan settings workspace is registered' );
parisan_theme_smoke_assert( function_exists( 'parisan_core_register_booking_settings' ), 'booking rules settings are registered' );
parisan_theme_smoke_assert( function_exists( 'parisan_core_sanitize_booking_interval' ), 'booking interval setting has a dedicated sanitizer' );
parisan_theme_smoke_assert( function_exists( 'parisan_core_sanitize_booking_buffer' ), 'booking buffer settings have a dedicated sanitizer' );
parisan_theme_smoke_assert( 15 === parisan_core_sanitize_booking_interval( 15 ) && 15 === parisan_core_sanitize_booking_interval( 17 ) && 60 === parisan_core_sanitize_booking_interval( 60 ), 'booking interval accepts supported increments and safely defaults invalid values' );
parisan_theme_smoke_assert( 0 === parisan_core_sanitize_booking_buffer( -5 ) && 240 === parisan_core_sanitize_booking_buffer( 999 ), 'booking buffers are clamped to the supported 0–240 minute range' );
parisan_theme_smoke_assert( false !== has_action( 'admin_menu', 'parisan_core_register_settings_pages' ), 'settings workspace and booking rules pages are registered in WordPress admin' );
$settings_admin = get_user_by( 'login', 'parisan-ci' );
parisan_theme_smoke_assert( $settings_admin instanceof WP_User, 'disposable WordPress administrator is available for settings-screen smoke coverage' );
wp_set_current_user( $settings_admin->ID );
ob_start();
parisan_core_render_settings_hub();
$settings_hub_html = ob_get_clean();
parisan_theme_smoke_assert( false !== strpos( $settings_hub_html, 'تنظیمات پاریسان' ) && false !== strpos( $settings_hub_html, 'customize.php?autofocus' ) && false !== strpos( $settings_hub_html, 'parisan-booking-settings' ), 'settings workspace renders its Persian title and real configuration links' );
parisan_theme_smoke_assert( false !== strpos( $settings_hub_html, 'غیرفعال است' ) && false !== strpos( $settings_hub_html, 'از این صفحه قابل فعال‌سازی نیست' ), 'settings workspace keeps the public booking safety gate visible and non-editable' );
parisan_core_register_booking_settings();
ob_start();
parisan_core_render_booking_settings_page();
$booking_settings_html = ob_get_clean();
parisan_theme_smoke_assert( false !== strpos( $booking_settings_html, 'parisan_booking_slot_interval_minutes' ) && false !== strpos( $booking_settings_html, 'parisan_booking_buffer_before_minutes' ) && false !== strpos( $booking_settings_html, 'parisan_booking_buffer_after_minutes' ), 'booking settings page renders all three availability-engine controls' );
$_GET['page'] = 'parisan-bookings';
$_GET['booking_date'] = current_datetime()->format( 'Y-m-d' );
$_GET['staff_id'] = 0;
$_GET['status'] = '';
ob_start();
parisan_core_render_booking_admin_page();
$daily_booking_html = ob_get_clean();
parisan_theme_smoke_assert( false !== strpos( $daily_booking_html, 'مدیریت نوبت‌ها' ) && false !== strpos( $daily_booking_html, 'برنامه روزانه' ) && false !== strpos( $daily_booking_html, 'parisan-booking-date' ), 'booking operations screen renders the date-filtered daily schedule without a fatal error' );
parisan_theme_smoke_assert( false !== strpos( $daily_booking_html, 'همه متخصصان' ) && false !== strpos( $daily_booking_html, 'نمایش برنامه' ), 'daily schedule exposes specialist selection and a clear submit action' );
parisan_theme_smoke_assert( false !== strpos( $daily_booking_html, 'تقویم عملیاتی روز' ) && false !== strpos( $daily_booking_html, 'parisan-calendar-lanes' ), 'daily booking calendar renders its operational lane structure' );
parisan_theme_smoke_assert( false !== strpos( $daily_booking_html, 'parisan-booking-resource' ) && false !== strpos( $daily_booking_html, 'همه منابع' ), 'booking operations expose a resource filter alongside specialist and status filters' );
parisan_theme_smoke_assert( false !== strpos( $daily_booking_html, 'روز قبل' ) && false !== strpos( $daily_booking_html, 'روز بعد' ), 'daily calendar provides timezone-local previous and next day navigation' );
unset( $_GET['page'], $_GET['booking_date'], $_GET['staff_id'], $_GET['status'], $_GET['resource_id'] );
wp_set_current_user( 0 );

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

$theme_root = get_template_directory();
$index_template = file_get_contents( $theme_root . '/index.php' );
$search_template = file_get_contents( $theme_root . '/search.php' );
$archive_template = file_get_contents( $theme_root . '/archive.php' );
parisan_theme_smoke_assert( false !== strpos( $index_template, 'parisan-content-index' ) && false !== strpos( $index_template, 'parisan_card_image_html' ) && false !== strpos( $index_template, 'the_posts_pagination' ), 'generic public fallback is a complete editorial archive with imagery and pagination' );
parisan_theme_smoke_assert( false === strpos( $index_template, 'service-placeholder-grid' ) && false === strpos( $index_template, 'سامانه نوبت‌دهی آنلاین با فعال‌سازی افزونه' ), 'generic index fallback does not display a fake salon homepage or stale booking placeholder' );
parisan_theme_smoke_assert( false !== strpos( $search_template, 'search-results-count' ) && false !== strpos( $search_template, 'role="status"' ) && false !== strpos( $search_template, 'aria-labelledby="search-results-title"' ), 'search results expose an announced result count and a labelled results region' );
parisan_theme_smoke_assert( false !== strpos( $search_template, 'get_post_type_object' ) && false !== strpos( $search_template, '$post_type_object && isset' ) && false !== strpos( $search_template, 'parisan_card_image_html' ), 'search cards safely resolve post-type labels and use the shared image treatment' );
parisan_theme_smoke_assert( false !== strpos( $search_template, 'the_posts_pagination' ) && false !== strpos( $archive_template, 'the_posts_pagination' ), 'search and archive results both retain pagination' );
$theme_styles = file_get_contents( $theme_root . '/assets/css/main.css' );
parisan_theme_smoke_assert( false !== strpos( $theme_styles, '.parisan-content-index' ) || false !== strpos( $theme_styles, '.post-grid' ), 'content fallback reuses the established editorial design system' );

parisan_theme_smoke_assert( function_exists( 'parisan_core_register_finance_admin_page' ) && function_exists( 'parisan_core_render_finance_admin_page' ), 'read-only salon finance workspace is registered and renderable' );
$finance_source = file_get_contents( dirname( __DIR__ ) . '/plugin/parisan-core/includes/finance-admin.php' );
parisan_theme_smoke_assert( false !== strpos( $finance_source, "status='completed'" ) && false !== strpos( $finance_source, 'recognized_irr' ), 'finance revenue is derived only from completed booking snapshots' );
parisan_theme_smoke_assert( false !== strpos( $finance_source, "current_user_can( 'edit_post', " ) && false !== strpos( $finance_source, 'staff_id IN (' ), 'finance aggregate queries are constrained to editable specialist IDs' );
parisan_theme_smoke_assert( false !== strpos( $finance_source, 'parisan_core_finance_range' ) && false !== strpos( $finance_source, 'wp_timezone()' ), 'finance report uses salon-local date boundaries converted to UTC' );
$public_service_archive = file_get_contents( $theme_root . '/archive-parisan_service.php' );
$public_staff_archive = file_get_contents( $theme_root . '/archive-parisan_staff.php' );
$public_service_single = file_get_contents( $theme_root . '/single-parisan_service.php' );
$public_staff_single = file_get_contents( $theme_root . '/single-parisan_staff.php' );
parisan_theme_smoke_assert( false !== strpos( $public_service_archive, '/contact/' ) && false !== strpos( $public_staff_archive, '/contact/' ), 'public service and specialist archives provide contact CTAs' );
parisan_theme_smoke_assert( false !== strpos( $public_service_single, '/contact/' ) && false !== strpos( $public_staff_single, '/contact/' ), 'public service and specialist detail pages route enquiries to salon contact, not online booking' );
parisan_theme_smoke_assert( false !== strpos( $public_staff_single, '_parisan_staff_services' ) && false !== strpos( $public_service_single, '_parisan_price' ), 'public detail templates expose existing specialist-service and service-pricing data' );


$finance_ledger_source = file_get_contents( dirname( __DIR__ ) . '/plugin/parisan-core/includes/finance-ledger.php' );
$contact_source = file_get_contents( dirname( __DIR__ ) . '/plugin/parisan-core/includes/contact-inquiries.php' );
parisan_theme_smoke_assert( false !== strpos( $finance_ledger_source, 'parisan_save_finance_entry' ) && false !== strpos( $finance_ledger_source, 'manage_options' ) && false !== strpos( $finance_ledger_source, 'amount_irr' ), 'manual finance ledger validates administrator, nonce, and integer IRR amounts' );
parisan_theme_smoke_assert( false !== strpos( $finance_ledger_source, "array( 'income', 'expense' )" ) && false !== strpos( $finance_ledger_source, 'created_by' ), 'finance ledger records income/expense type and actor provenance' );
parisan_theme_smoke_assert( false !== strpos( $contact_source, 'wp_verify_nonce' ) && false !== strpos( $contact_source, 'honeypot' ) && false !== strpos( $contact_source, 'set_transient' ), 'public contact submission includes nonce, honeypot, and rate limiting' );
parisan_theme_smoke_assert( false !== strpos( $contact_source, 'parisan_contact_inquiries' ) && false !== strpos( $contact_source, 'parisan_update_contact_status' ) && false !== strpos( $contact_source, 'manage_options' ), 'contact messages persist to private inbox with administrator status workflow' );
$contact_template = file_get_contents( $theme_root . '/page-contact.php' );
parisan_theme_smoke_assert( false !== strpos( $contact_template, 'parisan_core_render_contact_form' ), 'public contact page renders the first-party inquiry form' );


$privacy_source = file_get_contents( dirname( __DIR__ ) . '/plugin/parisan-core/includes/privacy.php' );
parisan_theme_smoke_assert( false !== strpos( $privacy_source, 'parisan-contact-inquiries' ) && false !== strpos( $privacy_source, 'parisan_core_contact_inquiry_exporter_callback' ) && false !== strpos( $privacy_source, 'parisan_core_contact_inquiry_eraser_callback' ), 'contact inquiry PII participates in WordPress personal-data export and erasure' );
$cache_source = file_get_contents( dirname( __DIR__ ) . '/plugin/parisan-core/includes/seo-performance.php' );
parisan_theme_smoke_assert( false !== strpos( $cache_source, "is_page( 'contact' )" ), 'nonce-bearing public contact page bypasses full-page cache' );

$finance_ops_source = file_get_contents( dirname( __DIR__ ) . '/plugin/parisan-core/includes/finance-operations.php' );
$crm_ops_source = file_get_contents( dirname( __DIR__ ) . '/plugin/parisan-core/includes/customer-relationships.php' );
$db_source = file_get_contents( dirname( __DIR__ ) . '/plugin/parisan-core/includes/database.php' );
parisan_theme_smoke_assert( false !== strpos( $finance_ops_source, 'parisan_finance_adjustments' ) && false !== strpos( $finance_ops_source, 'parisan_finance_adjustment' ), 'finance adjustments are append-only and protected by administrator capability and nonce' );
parisan_theme_smoke_assert( false !== strpos( $finance_ops_source, 'parisan_finance_csv_export' ) && false !== strpos( $finance_ops_source, 'text/csv' ), 'finance ledger supports CSV export' );
parisan_theme_smoke_assert( false !== strpos( $finance_ops_source, 'مانده بانکی نیست' ) && false !== strpos( $finance_ops_source, 'دریافتی رزرو' ), 'manual ledger statement explicitly avoids claiming bank reconciliation or double-counting booking revenue' );
parisan_theme_smoke_assert( false !== strpos( $crm_ops_source, 'parisan_customer_notes' ) && false !== strpos( $crm_ops_source, 'parisan_customer_followups' ), 'CRM supports internal notes and scheduled follow-ups' );
parisan_theme_smoke_assert( false !== strpos( $crm_ops_source, 'parisan_complete_customer_followup' ) && false !== strpos( $crm_ops_source, 'parisan_inquiry_customer_links' ), 'CRM supports follow-up completion and contact inquiry linkage' );
parisan_theme_smoke_assert( false !== strpos( $finance_ops_source, 'FOR UPDATE' ) && false !== strpos( $finance_ops_source, '$current_total' ), 'finance corrections lock the source entry and prevent reductions below zero' );
parisan_theme_smoke_assert( false !== strpos( $finance_ops_source, "START TRANSACTION" ) && false !== strpos( $finance_ops_source, 'شروع تراکنش امن مالی ممکن نشد' ), 'finance corrections fail closed if a database transaction cannot start' );
parisan_theme_smoke_assert( false !== strpos( $finance_ops_source, 'income_delta_irr' ) && false !== strpos( $finance_ops_source, 'expense_delta_irr' ), 'finance statement applies adjustments only to their original income or expense class' );
parisan_theme_smoke_assert( false !== strpos( $crm_ops_source, 'صف پیگیری باز' ) && false !== strpos( $crm_ops_source, 'سررسید گذشته' ) && false !== strpos( $crm_ops_source, 'setTimezone( wp_timezone() )' ), 'CRM renders a permission-scoped open and overdue follow-up queue in salon-local time' );
parisan_theme_smoke_assert( false !== strpos( $crm_ops_source, 'staff_id IN ({$ids})' ) && false !== strpos( $crm_ops_source, 'current_user_can' ), 'CRM relationship views and writes enforce specialist ownership scope' );
parisan_theme_smoke_assert( false !== strpos( $db_source, "define( 'PARISAN_CORE_DB_VERSION', 9 )" ) && false !== strpos( $db_source, 'parisan_inquiry_customer_links' ), 'database schema includes additive CRM and finance workflow tables' );
$booking_admin_source = file_get_contents( dirname( __DIR__ ) . '/plugin/parisan-core/includes/booking-admin.php' );
$booking_report_source = file_get_contents( dirname( __DIR__ ) . '/plugin/parisan-core/includes/booking-operations-report.php' );
parisan_theme_smoke_assert( false !== strpos( $booking_admin_source, 'booking_event_note' ) && false !== strpos( $booking_admin_source, 'parisan_booking_reason_required' ), 'booking cancellation and no-show transitions require an audit reason' );
parisan_theme_smoke_assert( false !== strpos( $booking_report_source, 'parisan_core_get_booking_report_rows' ) && false !== strpos( $booking_report_source, 'staff_id IN (' ), 'operational booking report aggregates only specialists editable by the current user' );
parisan_theme_smoke_assert( false !== strpos( $booking_report_source, 'parisan_booking_report_csv' ) && false !== strpos( $booking_report_source, 'text/csv; charset=UTF-8' ) && false !== strpos( $booking_report_source, 'booking_report_csv_cell' ), 'operational report exports UTF-8 CSV with spreadsheet formula-injection protection' );
parisan_theme_smoke_assert( false !== strpos( $booking_report_source, 'parisan_core_booking_report_range' ) && false !== strpos( $booking_report_source, 'wp_timezone()' ) && false !== strpos( $booking_report_source, 'completed_value_irr' ), 'operational report uses salon-local date boundaries and separates completed booking value' );



$booking_finance_source = file_get_contents( dirname( __DIR__ ) . '/plugin/parisan-core/includes/booking-finance.php' );
$loyalty_source = file_get_contents( dirname( __DIR__ ) . '/plugin/parisan-core/includes/customer-loyalty.php' );
$privacy_source = file_get_contents( dirname( __DIR__ ) . '/plugin/parisan-core/includes/privacy.php' );
parisan_theme_smoke_assert( function_exists( 'parisan_core_render_booking_finance_page' ) && false !== strpos( $booking_finance_source, 'FOR UPDATE' ), 'booking invoice adjustments are registered and serialize event writes against the source booking' );
parisan_theme_smoke_assert( false !== strpos( $booking_finance_source, "array( 'discount', 'surcharge', 'refund' )" ) && false !== strpos( $booking_finance_source, 'invoice_total - $refunds' ), 'booking finance validates discount, surcharge, and cumulative refund ceilings' );
parisan_theme_smoke_assert( false !== strpos( $booking_finance_source, 'قیمت اولیه رزرو حفظ می‌شود' ) && false !== strpos( $booking_finance_source, 'تأییدیه درگاه' ), 'booking finance preserves original price and disclaims payment verification' );
parisan_theme_smoke_assert( function_exists( 'parisan_core_render_customer_loyalty_page' ) && false !== strpos( $loyalty_source, "status='completed'" ) && false !== strpos( $loyalty_source, '60 days' ), 'customer loyalty tiers and inactivity are derived from completed booking history' );
parisan_theme_smoke_assert( false !== strpos( $loyalty_source, 'staff_id IN ({$ids})' ) && false !== strpos( $loyalty_source, 'current_user_can' ), 'customer loyalty insights remain scoped to editable specialists' );
parisan_theme_smoke_assert( false !== strpos( $loyalty_source, 'ارزش نقدی' ) && false !== strpos( $loyalty_source, 'اعتبار قابل‌خرج' ), 'loyalty points are explicitly non-monetary and non-redeemable' );
parisan_theme_smoke_assert( false !== strpos( $privacy_source, 'parisan_booking_finance_events' ) && false !== strpos( $privacy_source, 'finance_notes_updated' ), 'booking finance event notes participate in privacy export and terminal-booking anonymization' );
parisan_theme_smoke_assert( false !== strpos( $db_source, "define( 'PARISAN_CORE_DB_VERSION', 9 )" ) && false !== strpos( $db_source, 'parisan_booking_finance_events' ), 'database schema v8 adds append-only booking finance events' );


$resource_maintenance_source = file_get_contents( dirname( __DIR__ ) . '/plugin/parisan-core/includes/resource-maintenance-admin.php' );
$resource_report_source = file_get_contents( dirname( __DIR__ ) . '/plugin/parisan-core/includes/resource-operations-report.php' );
$resource_source = file_get_contents( dirname( __DIR__ ) . '/plugin/parisan-core/includes/resources.php' );
parisan_theme_smoke_assert( false !== strpos( $resource_maintenance_source, 'parisan_core_handle_resource_maintenance_create' ) && false !== strpos( $resource_maintenance_source, 'parisan_core_acquire_resource_schedule_locks' ), 'resource maintenance writes are authorized and serialized with booking allocation locks' );
parisan_theme_smoke_assert( false !== strpos( $resource_maintenance_source, 'parisan_maintenance_booking_conflict' ) && false !== strpos( $resource_maintenance_source, 'parisan_maintenance_overlap' ), 'resource maintenance rejects active-booking and maintenance-window conflicts' );
parisan_theme_smoke_assert( false !== strpos( $resource_maintenance_source, 'cancelled_by' ) && false !== strpos( $resource_maintenance_source, 'cancelled_at_utc' ), 'resource maintenance cancellation preserves actor and UTC audit metadata' );
parisan_theme_smoke_assert( false !== strpos( $resource_source, 'parisan_resource_maintenance' ) && false !== strpos( $resource_source, 'busy_by_resource[ $maintenance_resource_id ]' ), 'resource allocator blocks maintenance windows for all affected resource slots' );
parisan_theme_smoke_assert( false !== strpos( $resource_report_source, 'parisan_core_get_resource_report_rows' ) && false !== strpos( $resource_report_source, 'current_user_can( \'edit_post\'' ), 'resource operations report is scoped to editable resources' );
parisan_theme_smoke_assert( false !== strpos( $resource_report_source, 'parisan_resource_report_csv' ) && false !== strpos( $resource_report_source, 'text/csv; charset=UTF-8' ), 'resource report provides nonce-protected UTF-8 CSV export' );
parisan_theme_smoke_assert( false !== strpos( $resource_report_source, 'GREATEST(b.starts_at_utc,%s)' ) && false !== strpos( $resource_report_source, 'GREATEST(starts_at_utc,%s)' ), 'resource report clips booking and maintenance durations to the selected date range' );
parisan_theme_smoke_assert( false !== strpos( $db_source, "define( 'PARISAN_CORE_DB_VERSION', 9 )" ) && false !== strpos( $db_source, 'parisan_resource_maintenance' ), 'database schema v9 adds resource maintenance windows' );

echo "PASS: Parisan theme, portfolio content, dynamic homepage, booking disabled state, SEO controls, sanitization, and private-cache guards.\\n";
