<?php
/**
 * Isolated tests for Parisan cache-provider adapters.
 *
 * @package ParisanCore
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'WP_CONTENT_DIR', __DIR__ . '/parisan-cache-test-content' );
$GLOBALS['parisan_test_external_cache'] = false;

$GLOBALS['parisan_test_hooks'] = array();
$GLOBALS['parisan_test_purges'] = array();

function absint( $value ) {
	return abs( (int) $value );
}

function wp_using_ext_object_cache() {
	return $GLOBALS['parisan_test_external_cache'];
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['parisan_test_hooks'][ $hook ][ $priority ][] = array( $callback, $accepted_args );
}

function has_action( $hook ) {
	return empty( $GLOBALS['parisan_test_hooks'][ $hook ] ) ? false : 10;
}

function do_action( $hook, ...$args ) {
	if ( empty( $GLOBALS['parisan_test_hooks'][ $hook ] ) ) {
		return;
	}
	foreach ( $GLOBALS['parisan_test_hooks'][ $hook ] as $callbacks ) {
		foreach ( $callbacks as $entry ) {
			call_user_func_array( $entry[0], array_slice( $args, 0, $entry[1] ) );
		}
	}
}

require_once __DIR__ . '/../plugin/parisan-core/includes/cache-integration.php';

function parisan_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

$runtime_diagnostics = parisan_core_cache_runtime_diagnostics();
parisan_test_assert( false === $runtime_diagnostics['persistent_object_cache_active'], 'Runtime diagnostics must report the external object cache flag accurately.' );
parisan_test_assert( false === $runtime_diagnostics['object_cache_dropin_present'], 'Runtime diagnostics must not invent an object-cache drop-in.' );
$GLOBALS['parisan_test_external_cache'] = true;
$runtime_diagnostics = parisan_core_cache_runtime_diagnostics();
parisan_test_assert( true === $runtime_diagnostics['persistent_object_cache_active'], 'Runtime diagnostics did not reflect the external object cache flag.' );
$GLOBALS['parisan_test_external_cache'] = false;

$cache_test_dir = WP_CONTENT_DIR;
if ( ! is_dir( $cache_test_dir ) ) {
	mkdir( $cache_test_dir, 0700, true );
}
$dropin_file = $cache_test_dir . DIRECTORY_SEPARATOR . 'object-cache.php';
file_put_contents( $dropin_file, "<?php // disposable cache diagnostics fixture\n" );
$runtime_diagnostics = parisan_core_cache_runtime_diagnostics();
parisan_test_assert( true === $runtime_diagnostics['object_cache_dropin_present'], 'Runtime diagnostics did not detect a present object-cache drop-in.' );
unlink( $dropin_file );
rmdir( $cache_test_dir );

$empty_status = parisan_core_cache_adapter_status();
parisan_test_assert( false === $empty_status['wp_rocket']['detected'], 'WP Rocket must be reported absent before its API is loaded.' );
parisan_test_assert( false === $empty_status['litespeed_cache']['detected'], 'LiteSpeed must be reported absent without its purge hook.' );
parisan_test_assert( false === $empty_status['w3_total_cache']['detected'], 'W3 Total Cache must be reported absent before its API is loaded.' );

if ( ! function_exists( 'rocket_clean_post' ) ) {
	function rocket_clean_post( $post_id ) {
		$GLOBALS['parisan_test_purges']['wp_rocket'][] = $post_id;
	}
}

if ( ! function_exists( 'w3tc_flush_post' ) ) {
	function w3tc_flush_post( $post_id, $force = false, $extras = null ) {
		$GLOBALS['parisan_test_purges']['w3_total_cache'][] = $post_id;
	}
}

function parisan_test_litespeed_purge( $post_id ) {
	$GLOBALS['parisan_test_purges']['litespeed_cache'][] = $post_id;
}
add_action( 'litespeed_purge_post', 'parisan_test_litespeed_purge', 10, 1 );

$runtime_diagnostics = parisan_core_cache_runtime_diagnostics();
parisan_test_assert( true === $runtime_diagnostics['provider_adapters']['wp_rocket']['detected'], 'Runtime diagnostics did not report WP Rocket adapter availability.' );
parisan_test_assert( true === $runtime_diagnostics['provider_adapters']['litespeed_cache']['detected'], 'Runtime diagnostics did not report LiteSpeed adapter availability.' );
parisan_test_assert( true === $runtime_diagnostics['provider_adapters']['w3_total_cache']['detected'], 'Runtime diagnostics did not report W3 Total Cache adapter availability.' );

$status = parisan_core_cache_adapter_status();
parisan_test_assert( true === $status['wp_rocket']['detected'], 'WP Rocket adapter detection failed.' );
parisan_test_assert( true === $status['litespeed_cache']['detected'], 'LiteSpeed adapter detection failed.' );
parisan_test_assert( true === $status['w3_total_cache']['detected'], 'W3 Total Cache adapter detection failed.' );

$outcomes = parisan_core_purge_public_post( 42 );
parisan_test_assert( 3 === count( $outcomes ), 'All three detected providers should receive one targeted request.' );
foreach ( array( 'wp_rocket', 'litespeed_cache', 'w3_total_cache' ) as $provider ) {
	parisan_test_assert( 'requested' === $outcomes[ $provider ], 'Provider request was not reported for ' . $provider . '.' );
	parisan_test_assert( array( 42 ) === $GLOBALS['parisan_test_purges'][ $provider ], 'Provider did not receive exactly the requested post ID: ' . $provider . '.' );
}

do_action( 'parisan_core_public_content_cache_invalidated', 77 );
foreach ( array( 'wp_rocket', 'litespeed_cache', 'w3_total_cache' ) as $provider ) {
	parisan_test_assert( array( 42, 77 ) === $GLOBALS['parisan_test_purges'][ $provider ], 'Invalidation event did not dispatch the post ID to ' . $provider . '.' );
}

$before = $GLOBALS['parisan_test_purges'];
$invalid = parisan_core_purge_public_post( 0 );
parisan_test_assert( array() === $invalid, 'Invalid post IDs must not trigger provider purges.' );
parisan_test_assert( $before === $GLOBALS['parisan_test_purges'], 'Invalid post ID unexpectedly triggered a provider purge.' );

echo "Parisan cache adapter tests passed (provider detection, targeted purge, invalid ID guard)." . PHP_EOL;
