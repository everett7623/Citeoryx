<?php
/**
 * WordPress test environment version resolver tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Build\WordPressTestVersionResolver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once dirname( __DIR__, 2 ) . '/bin/resolve-wp-test-versions.php';

/**
 * Verifies core packages and wordpress-develop tags stay aligned.
 */
class WordPressTestVersionResolverTest extends TestCase {

	/**
	 * Sample version-check response ordered like the live WordPress API.
	 *
	 * @return array<string, mixed>
	 */
	private function response(): array {
		return array(
			'offers' => array(
				array( 'version' => '7.1' ),
				array( 'version' => '7.0.4' ),
				array( 'version' => '6.6.7' ),
			),
		);
	}

	public function test_latest_major_release_uses_dot_zero_test_tag(): void {
		$this->assertSame(
			array(
				'core'  => '7.1',
				'tests' => '7.1.0',
			),
			WordPressTestVersionResolver::resolve( $this->response(), 'latest' )
		);
	}

	public function test_exact_major_minor_release_is_supported(): void {
		$this->assertSame(
			array(
				'core'  => '7.1',
				'tests' => '7.1.0',
			),
			WordPressTestVersionResolver::resolve( $this->response(), '7.1' )
		);
	}

	public function test_release_line_selects_latest_patch_offer(): void {
		$this->assertSame(
			array(
				'core'  => '6.6.7',
				'tests' => '6.6.7',
			),
			WordPressTestVersionResolver::resolve( $this->response(), '6.6' )
		);
	}

	public function test_explicit_patch_release_does_not_require_api_offer(): void {
		$this->assertSame(
			array(
				'core'  => '7.0.3',
				'tests' => '7.0.3',
			),
			WordPressTestVersionResolver::resolve( $this->response(), '7.0.3' )
		);
	}

	public function test_unknown_release_is_rejected(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Unsupported WordPress version: 5.0' );

		WordPressTestVersionResolver::resolve( $this->response(), '5.0' );
	}
}
