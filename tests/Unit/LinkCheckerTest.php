<?php
/**
 * Link checker tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Application\Scan\LinkChecker;
use Citeoryx\Domain\Link\Link;
use Citeoryx\Domain\Link\LinkRepository;
use Citeoryx\Infrastructure\Http\HttpClient;
use WP_UnitTestCase;

/**
 * Tests for bounded external link checks.
 */
class LinkCheckerTest extends WP_UnitTestCase {

	public function test_batch_uses_cursor_and_falls_back_to_get(): void {
		$link              = new Link();
		$link->id          = 12;
		$link->target_url  = 'https://example.com/article';
		$link->is_internal = false;

		$repo = new class( $link ) extends LinkRepository {
			private Link $link;
			public int $after_id = 0;
			public int $status   = 0;

			public function __construct( Link $link ) {
				$this->link = $link;
			}

			public function get_for_status_check( int $limit = 50, int $after_id = 0 ): array {
				$this->after_id = $after_id;
				return array( $this->link );
			}

			public function update_status( int $id, int $status, string $error = '' ): void {
				$this->status = $status;
			}
		};

		$http = new class() extends HttpClient {
			public int $get_calls = 0;

			public function head( string $url, array $args = array() ): array {
				return array(
					'success' => false,
					'body'    => '',
					'code'    => 405,
					'error'   => '',
				);
			}

			public function get( string $url, array $args = array() ): array {
				++$this->get_calls;
				return array(
					'success' => true,
					'body'    => '',
					'code'    => 200,
					'error'   => '',
				);
			}
		};

		$result = ( new LinkChecker( $repo, $http ) )->check_batch( 5, 10 );

		$this->assertSame( 10, $repo->after_id );
		$this->assertSame( 1, $http->get_calls );
		$this->assertSame( 200, $repo->status );
		$this->assertSame( 12, $result['last_id'] );
	}
}
