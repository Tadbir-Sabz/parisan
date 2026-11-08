<?php
/**
 * WordPress integration smoke test for breadcrumbs and BreadcrumbList schema.
 *
 * @package ParisanCore
 */

function parisan_test_breadcrumb_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

if ( ! function_exists( 'parisan_core_get_breadcrumb_items' ) || ! function_exists( 'parisan_core_output_breadcrumb_schema' ) ) {
	fwrite( STDERR, "Breadcrumb module was not loaded." . PHP_EOL );
	exit( 1 );
}

$service_id = wp_insert_post(
	array(
		'post_type'   => 'parisan_service',
		'post_status' => 'publish',
		'post_title'  => 'Synthetic breadcrumb service',
	),
	true
);
parisan_test_breadcrumb_assert( ! is_wp_error( $service_id ), 'Could not create the synthetic service.' );

$query = new WP_Query(
	array(
		'post_type'      => 'parisan_service',
		'p'              => $service_id,
		'post_status'    => 'publish',
		'posts_per_page' => 1,
	)
);
parisan_test_breadcrumb_assert( $query->have_posts(), 'Synthetic service query returned no post.' );
$GLOBALS['wp_query'] = $query;
$GLOBALS['post'] = $query->posts[0];
setup_postdata( $GLOBALS['post'] );

$items = parisan_core_get_breadcrumb_items();
parisan_test_breadcrumb_assert( 3 === count( $items ), 'Service detail should have Home, service archive, and current service breadcrumbs.' );
parisan_test_breadcrumb_assert( 'خانه' === $items[0]['label'], 'The first breadcrumb must be the Persian Home label.' );
parisan_test_breadcrumb_assert( 'Synthetic breadcrumb service' === $items[2]['label'], 'The current breadcrumb must use the service title.' );
parisan_test_breadcrumb_assert( '' === $items[2]['url'], 'The current breadcrumb must not be a link.' );

ob_start();
parisan_core_render_breadcrumbs();
$markup = ob_get_clean();
parisan_test_breadcrumb_assert( false !== strpos( $markup, '<nav' ), 'Semantic breadcrumb navigation was not rendered.' );
parisan_test_breadcrumb_assert( false !== strpos( $markup, 'aria-current="page"' ), 'Current breadcrumb is missing aria-current.' );
parisan_test_breadcrumb_assert( false !== strpos( $markup, 'مسیر صفحه' ), 'Breadcrumb navigation label is not Persian.' );

ob_start();
parisan_core_output_breadcrumb_schema();
$schema_markup = ob_get_clean();
parisan_test_breadcrumb_assert( false !== strpos( $schema_markup, 'BreadcrumbList' ), 'BreadcrumbList JSON-LD was not emitted.' );
$matches = array();
preg_match( '/<script type="application\/ld\+json">(.*?)<\/script>/s', $schema_markup, $matches );
$schema = isset( $matches[1] ) ? json_decode( $matches[1], true ) : null;
parisan_test_breadcrumb_assert( is_array( $schema ) && 'BreadcrumbList' === $schema['@type'], 'BreadcrumbList JSON-LD is invalid.' );
parisan_test_breadcrumb_assert( 3 === count( $schema['itemListElement'] ), 'BreadcrumbList positions are incomplete.' );
parisan_test_breadcrumb_assert( get_permalink( $service_id ) === $schema['itemListElement'][2]['item'], 'The current breadcrumb schema item must use the canonical service URL.' );

$visible_service_id = wp_insert_post(
	array(
		'post_type'   => 'parisan_service',
		'post_status' => 'publish',
		'post_title'  => 'Synthetic sitemap-visible service',
	),
	true
);
$noindex_service_id = wp_insert_post(
	array(
		'post_type'   => 'parisan_service',
		'post_status' => 'publish',
		'post_title'  => 'Synthetic sitemap-hidden service',
	),
	true
);
parisan_test_breadcrumb_assert( ! is_wp_error( $visible_service_id ) && ! is_wp_error( $noindex_service_id ), 'Could not create synthetic sitemap services.' );
update_post_meta( $noindex_service_id, '_parisan_seo_noindex', '1' );

$sitemap_args = apply_filters(
	'wp_sitemaps_posts_query_args',
	array(
		'post_type'   => 'parisan_service',
		'post_status' => array( 'publish' ),
	),
	'parisan_service'
);
$preserved_meta_args = apply_filters(
	'wp_sitemaps_posts_query_args',
	array(
		'post_type'   => 'parisan_service',
		'post_status' => array( 'publish' ),
		'meta_query'  => array(
			'relation' => 'OR',
			array(
				'key'   => '_parisan_test_flag',
			'value' => 'yes',
			),
			array(
				'key'   => '_parisan_other_flag',
			'value' => 'yes',
			),
		),
	),
	'parisan_service'
);
parisan_test_breadcrumb_assert( 'AND' === $preserved_meta_args['meta_query']['relation'], 'Noindex exclusion must be ANDed with existing sitemap meta-query constraints.' );
parisan_test_breadcrumb_assert( 'OR' === $preserved_meta_args['meta_query'][0]['relation'], 'Existing nested OR meta-query semantics were not preserved.' );
parisan_test_breadcrumb_assert( 'OR' === $preserved_meta_args['meta_query'][1]['relation'], 'Noindex exclusion must remain a nested OR clause.' );

$sitemap_query = new WP_Query( $sitemap_args );
$sitemap_ids = wp_list_pluck( $sitemap_query->posts, 'ID' );
parisan_test_breadcrumb_assert( in_array( (int) $visible_service_id, $sitemap_ids, true ), 'Indexable service was unexpectedly excluded from the core sitemap.' );
parisan_test_breadcrumb_assert( ! in_array( (int) $noindex_service_id, $sitemap_ids, true ), 'Noindex service was not excluded from the core sitemap.' );

if ( ! defined( 'RANK_MATH_VERSION' ) ) {
	define( 'RANK_MATH_VERSION', 'parisan-ci-test' );
}
$plugin_owned_sitemap_args = array(
	'post_type'   => 'parisan_service',
	'post_status' => array( 'publish' ),
);
parisan_test_breadcrumb_assert(
	$plugin_owned_sitemap_args === apply_filters( 'wp_sitemaps_posts_query_args', $plugin_owned_sitemap_args, 'parisan_service' ),
	'Parisan sitemap filtering must yield when a supported SEO plugin owns metadata output.'
);

ob_start();
parisan_core_output_breadcrumb_schema();
$plugin_owned_schema = ob_get_clean();
parisan_test_breadcrumb_assert( '' === $plugin_owned_schema, 'Breadcrumb schema must yield when a supported SEO plugin owns metadata/schema output.' );

$invalidated_ids = array();
add_action(
	'parisan_core_public_content_cache_invalidated',
	function ( $post_id ) use ( &$invalidated_ids ) {
		$invalidated_ids[] = (int) $post_id;
	},
	99,
	1
);
$ordinary_id = wp_insert_post(
	array(
		'post_type'   => 'post',
		'post_status' => 'publish',
		'post_title'  => 'Synthetic unrelated post',
	),
	true
);
parisan_test_breadcrumb_assert( ! is_wp_error( $ordinary_id ), 'Could not create unrelated synthetic post.' );
do_action( 'deleted_post', $ordinary_id, get_post( $ordinary_id ) );
parisan_test_breadcrumb_assert( empty( $invalidated_ids ), 'Unrelated post deletion must not trigger Parisan public cache invalidation.' );
do_action( 'deleted_post', $service_id, get_post( $service_id ) );
parisan_test_breadcrumb_assert( array( (int) $service_id ) === $invalidated_ids, 'Deleting a Parisan service must trigger exactly one targeted invalidation event.' );

echo "WordPress breadcrumb, sitemap noindex, and JSON-LD smoke tests passed." . PHP_EOL;
