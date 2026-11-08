<?php
/**
 * Targeted adapters for public full-page cache providers.
 *
 * Never purges an entire site. Providers are called only when their public
 * post-purge API is available, and private booking/customer contexts are
 * deliberately outside this integration.
 *
 * @package ParisanCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Report which supported post-level cache purge adapters are available.
 *
 * Detection is capability-based: a provider must expose its documented
 * integration point. This does not prove that a reverse proxy or CDN is purged.
 *
 * @return array
 */
function parisan_core_cache_adapter_status() {
	return array(
		'wp_rocket' => array(
			'detected' => function_exists( 'rocket_clean_post' ),
			'operation' => 'post',
		),
		'litespeed_cache' => array(
			'detected' => function_exists( 'has_action' ) && false !== has_action( 'litespeed_purge_post' ),
			'operation' => 'post',
		),
		'w3_total_cache' => array(
			'detected' => function_exists( 'w3tc_flush_post' ),
			'operation' => 'post',
		),
	);
}

/**
 * Request a targeted purge for one public Parisan content item.
 *
 * "requested" means the provider API was invoked; it is not proof that a
 * reverse proxy, CDN, or remote cache completed its purge.
 *
 * @param int $post_id Public content post ID.
 * @return array Provider request outcomes.
 */
function parisan_core_purge_public_post( $post_id ) {
	$post_id = absint( $post_id );
	if ( ! $post_id ) {
		return array();
	}

	$status = parisan_core_cache_adapter_status();
	$outcomes = array();

	if ( $status['wp_rocket']['detected'] ) {
		try {
			rocket_clean_post( $post_id );
			$outcomes['wp_rocket'] = 'requested';
		} catch ( Throwable $error ) {
			$outcomes['wp_rocket'] = 'error';
		}
	}

	if ( $status['litespeed_cache']['detected'] ) {
		try {
			do_action( 'litespeed_purge_post', $post_id );
			$outcomes['litespeed_cache'] = 'requested';
		} catch ( Throwable $error ) {
			$outcomes['litespeed_cache'] = 'error';
		}
	}

	if ( $status['w3_total_cache']['detected'] ) {
		try {
			w3tc_flush_post( $post_id );
			$outcomes['w3_total_cache'] = 'requested';
		} catch ( Throwable $error ) {
			$outcomes['w3_total_cache'] = 'error';
		}
	}

	return $outcomes;
}

/**
 * Connect provider adapters to Parisan's public-content invalidation event.
 *
 * @param int $post_id Updated/deleted public content ID.
 * @return void
 */
function parisan_core_dispatch_public_cache_purge( $post_id ) {
	parisan_core_purge_public_post( $post_id );
}
add_action( 'parisan_core_public_content_cache_invalidated', 'parisan_core_dispatch_public_cache_purge', 10, 1 );
