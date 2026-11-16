<?php
/**
 * Reproducible release-package builder for Parisan.
 *
 * Builds the theme, Parisan Core, and complete-source ZIPs from the tracked
 * files of the current Git revision, then writes checksums and a validation
 * manifest to dist/. The dist directory stays untracked.
 *
 * Usage:
 *   php tools/build-packages.php
 *
 * @package Parisan
 */

declare( strict_types = 1 );

$root = dirname( __DIR__ );
chdir( $root );

/**
 * Read NUL-separated tracked files from Git.
 *
 * @return string[]
 */
function parisan_tracked_files(): array {
	$output = shell_exec( 'git ls-files -z' );
	if ( ! is_string( $output ) || '' === $output ) {
		fwrite( STDERR, "Unable to read tracked files from Git.\n" );
		exit( 1 );
	}
	return array_values( array_filter( explode( "\0", $output ), 'strlen' ) );
}

/**
 * Create a ZIP from tracked files under a prefix.
 *
 * @param string   $zip_path Absolute ZIP path.
 * @param string[] $files    Repository-relative files.
 * @param string   $prefix   Repository-relative source prefix.
 * @param string   $root     ZIP root directory.
 * @return array{entries:int,php_files:int,lint_failures:string[]}
 */
function parisan_build_zip( string $zip_path, array $files, string $prefix, string $root ): array {
	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		fwrite( STDERR, "Unable to create {$zip_path}.\n" );
		exit( 1 );
	}

	$entries      = 0;
	$php_files    = 0;
	$lint_failures = array();
	foreach ( $files as $file ) {
		if ( 0 !== strpos( $file, $prefix ) ) {
			continue;
		}
		$relative = substr( $file, strlen( $prefix ) );
		if ( '' === $relative ) {
			continue;
		}
		$zip->addFile( $file, $root . '/' . $relative );
		++$entries;
		if ( 'php' === strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) ) {
			++$php_files;
			$code        = 0;
			$lint_output = array();
			exec( 'php -l ' . escapeshellarg( $file ) . ' 2>&1', $lint_output, $code );
			if ( 0 !== $code ) {
				$lint_failures[] = $file . ': ' . implode( ' ', $lint_output );
			}
		}
	}
	$zip->close();

	return array(
		'entries'       => $entries,
		'php_files'     => $php_files,
		'lint_failures' => $lint_failures,
	);
}

/**
 * Read a version declaration from a file.
 *
 * @param string $file Repository-relative file.
 * @param string $pattern Regular expression with one capture group.
 * @return string
 */
function parisan_read_version( string $file, string $pattern ): string {
	$contents = is_file( $file ) ? (string) file_get_contents( $file ) : '';
	if ( preg_match( $pattern, $contents, $matches ) ) {
		return $matches[1];
	}
	return 'unknown';
}

/**
 * Assert required and forbidden entries.
 *
 * @param string[] $entries ZIP entries relative to its root.
 * @param string   $kind    Package kind: theme, plugin, or source.
 * @return array{required_missing:string[],forbidden:string[]}
 */
function parisan_validate_entries( array $entries, string $kind ): array {
	$required = 'theme' === $kind
		? array( 'style.css', 'functions.php', 'index.php' )
		: ( 'plugin' === $kind ? array( 'parisan-core.php', 'readme.txt' ) : array() );
	$missing   = array();
	$forbidden = array();
	foreach ( $required as $name ) {
		if ( in_array( $name, $entries, true ) ) {
			continue;
		}
		$missing[] = $name;
	}
	foreach ( $entries as $entry ) {
		if ( preg_match( '#(^|/)(\.git|node_modules|dist|artifacts)(/|$)#', $entry )
			|| preg_match( '#(^|/)(wp-config\.php|\.env|.*\.sql|.*\.log)$#', $entry ) ) {
			$forbidden[] = $entry;
		}
	}
	return array( 'required_missing' => $missing, 'forbidden' => $forbidden );
}

$tracked = parisan_tracked_files();
$head    = trim( (string) shell_exec( 'git rev-parse HEAD' ) );
$head_override = getenv( 'PARISAN_BUILD_SHA' );
if ( is_string( $head_override ) && preg_match( '/^[a-f0-9]{40}$/', $head_override ) ) {
	$head = $head_override;
}
$dist    = $root . '/dist';
if ( ! is_dir( $dist ) && ! mkdir( $dist, 0777, true ) && ! is_dir( $dist ) ) {
	fwrite( STDERR, "Unable to create dist directory.\n" );
	exit( 1 );
}

$packages = array(
	'parisan-theme.zip'           => array(
		'prefix' => 'theme/parisan/',
		'root'   => 'parisan',
		'kind'   => 'theme',
	),
	'parisan-core.zip'            => array(
		'prefix' => 'plugin/parisan-core/',
		'root'   => 'parisan-core',
		'kind'   => 'plugin',
	),
	'parisan-complete-source.zip' => array(
		'prefix' => '',
		'root'   => 'parisan-source',
		'kind'   => 'source',
	),
);

$manifest = array(
	'generated_at' => gmdate( 'c' ),
	'git_head'     => $head,
	'packages'     => array(),
);
$checksums = array();

foreach ( $packages as $name => $package ) {
	$zip_path = $dist . '/' . $name;
	$result   = parisan_build_zip( $zip_path, $tracked, $package['prefix'], $package['root'] );
	$zip      = new ZipArchive();
	$zip_ok   = true === $zip->open( $zip_path, ZipArchive::CHECKCONS );
	if ( ! $zip_ok ) {
		fwrite( STDERR, "Unable to reopen {$zip_path}.\n" );
		exit( 1 );
	}
	$entries           = array();
	$git_mismatches    = array();
	for ( $index = 0; $index < $zip->numFiles; $index++ ) {
		$entry = $zip->getNameIndex( $index );
		if ( false === $entry ) {
			continue;
		}
		$relative  = substr( $entry, strlen( $package['root'] ) + 1 );
		$entries[] = $relative;
		$content   = $zip->getFromIndex( $index );
		$source    = $package['prefix'] . $relative;
		if ( false === $content || ! is_file( $source ) ) {
			$git_mismatches[] = $relative;
			continue;
		}
		$blob_hash = hash( 'sha1', 'blob ' . strlen( $content ) . "\0" . $content );
		$git_hash  = trim( (string) shell_exec( 'git hash-object ' . escapeshellarg( $source ) ) );
		if ( $blob_hash !== $git_hash ) {
			$git_mismatches[] = $relative;
		}
	}
	$zip->close();

	$validation = parisan_validate_entries( $entries, $package['kind'] );
	$sha256     = hash_file( 'sha256', $zip_path );
	$checksums[] = $sha256 . '  ' . $name;
	$manifest['packages'][] = array(
		'file'             => $name,
		'bytes'            => filesize( $zip_path ),
		'sha256'           => $sha256,
		'root'             => $package['root'] . '/',
		'kind'             => $package['kind'],
		'zip_integrity'    => $zip_ok,
		'entries'          => $result['entries'],
		'git_entries'      => count( $entries ),
		'git_blob_mismatches' => $git_mismatches,
		'php_files'        => $result['php_files'],
		'php_lint_failures'=> $result['lint_failures'],
		'required_missing' => $validation['required_missing'],
		'forbidden'        => $validation['forbidden'],
		'version'          => 'theme' === $package['kind']
			? parisan_read_version( 'theme/parisan/style.css', '/^Version:\s*(.+)$/m' )
			: ( 'plugin' === $package['kind'] ? parisan_read_version( 'plugin/parisan-core/parisan-core.php', '/^ \* Version:\s*(.+)$/m' ) : $head ),
	);
}

file_put_contents( $dist . '/SHA256SUMS.txt', implode( PHP_EOL, $checksums ) . PHP_EOL );
file_put_contents( $dist . '/package-validation.json', parisan_json_pretty( $manifest ) );
file_put_contents( $dist . '/delivery-manifest.json', parisan_json_pretty( $manifest ) );

echo "Built from {$head}\n";
foreach ( $manifest['packages'] as $package ) {
	echo sprintf(
		"%-30s %8d bytes  %s  entries=%d php=%d lint_failures=%d\n",
		$package['file'],
		$package['bytes'],
		$package['version'],
		$package['entries'],
		$package['php_files'],
		count( $package['php_lint_failures'] )
	);
}
echo "Checksums: dist/SHA256SUMS.txt\n";

/**
 * JSON pretty printer that works without WordPress.
 *
 * @param array<string,mixed> $value Value.
 * @return string
 */
function parisan_json_pretty( array $value ): string {
	$json = json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	return false === $json ? '{}' : $json . PHP_EOL;
}
