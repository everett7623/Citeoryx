<?php
/**
 * Resolve matching WordPress core and wordpress-develop test versions.
 *
 * @package Citeoryx\Build
 */

namespace Citeoryx\Build;

use RuntimeException;

/**
 * Resolves the distinct package versions used by the WordPress test installer.
 */
final class WordPressTestVersionResolver {

	/**
	 * Resolve a requested version against the WordPress version-check response.
	 *
	 * WordPress core omits `.0` from major releases (for example `7.1`), while
	 * wordpress-develop tags the same release as `7.1.0`.
	 *
	 * @param array<string, mixed> $response  Decoded version-check response.
	 * @param string               $requested Requested version or `latest`.
	 * @return array{core: string, tests: string}
	 */
	public static function resolve( array $response, string $requested ): array {
		$core_version = '';

		if ( preg_match( '/^[0-9]+\.[0-9]+\.[0-9]+$/', $requested ) ) {
			$core_version = $requested;
		} else {
			foreach ( $response['offers'] ?? array() as $offer ) {
				$version = isset( $offer['version'] ) ? (string) $offer['version'] : '';
				if (
					'latest' === $requested ||
					$version === $requested ||
					str_starts_with( $version, $requested . '.' )
				) {
					$core_version = $version;
					break;
				}
			}
		}

		if ( '' === $core_version ) {
			throw new RuntimeException( "Unsupported WordPress version: {$requested}" );
		}

		$tests_version = preg_match( '/^[0-9]+\.[0-9]+$/', $core_version )
			? $core_version . '.0'
			: $core_version;

		return array(
			'core'  => $core_version,
			'tests' => $tests_version,
		);
	}
}

if ( isset( $_SERVER['SCRIPT_FILENAME'] ) && realpath( (string) $_SERVER['SCRIPT_FILENAME'] ) === __FILE__ ) {
	if ( 3 !== $argc ) {
		fwrite( STDERR, "Usage: php resolve-wp-test-versions.php <versions-json> <requested-version>\n" );
		exit( 1 );
	}

	$response = json_decode( (string) file_get_contents( $argv[1] ), true );
	if ( ! is_array( $response ) ) {
		fwrite( STDERR, "Unable to parse WordPress version response.\n" );
		exit( 1 );
	}

	try {
		$resolved = WordPressTestVersionResolver::resolve( $response, $argv[2] );
	} catch ( RuntimeException $error ) {
		fwrite( STDERR, $error->getMessage() . "\n" );
		exit( 1 );
	}

	echo $resolved['core'] . "\n" . $resolved['tests'] . "\n";
}
