<?php
/**
 * Disposable WordPress smoke test for the Parisan public theme experience.
 *
 * @package Parisan
 */

defined( 'ABSPATH' ) || exit;

function parisan_theme_smoke_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

parisan_theme_smoke_assert( 'parisan' === get_stylesheet(), 'Parisan theme is active' );
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
$wp_query = new WP_Query(
	array(
		'post_type'      => 'page',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
	)
);
$post = null;

ob_start();
include get_template_directory() . '/front-page.php';
$homepage = ob_get_clean();

parisan_theme_smoke_assert( false !== strpos( $homepage, 'زیباییِ شما، با امضای ظرافت' ), 'editorial Persian hero renders' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'Synthetic Homepage Service' ), 'homepage renders real published service content' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'Synthetic Homepage Specialist' ), 'homepage renders real published specialist content' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'id="booking"' ), 'homepage exposes the booking anchor' );
parisan_theme_smoke_assert( false !== strpos( $homepage, 'رزرو آنلاین در حال حاضر فعال نیست' ), 'disabled booking shortcode displays its safe disabled state' );

echo "PASS: Parisan theme activation, fallback navigation, dynamic homepage content, and disabled booking state.\n";
