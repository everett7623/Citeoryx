<?php
/**
 * Aggregate REST response cache tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Domain\Content\ContentItem;
use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Domain\Issue\Issue;
use Citeoryx\Domain\Issue\IssueRepository;
use Citeoryx\Domain\Metrics\MetricsRepository;
use Citeoryx\Domain\Scan\ScanRun;
use Citeoryx\Domain\Scan\ScanRunRepository;
use Citeoryx\Infrastructure\Cache\RestResponseCache;
use Citeoryx\Infrastructure\Cache\Transients;
use WP_UnitTestCase;

/**
 * Verifies cache hits and repository-driven invalidation.
 */
class RestResponseCacheTest extends WP_UnitTestCase {

	private RestResponseCache $cache;

	public function setUp(): void {
		parent::setUp();
		RestResponseCache::flush();
		$this->cache = new RestResponseCache( new Transients() );
	}

	public function tearDown(): void {
		RestResponseCache::flush();
		parent::tearDown();
	}

	public function test_remember_reuses_payload_and_coalesces_invalidations(): void {
		$key        = $this->cache_key( 'remember' );
		$resolved   = 0;
		$resolver   = static function () use ( &$resolved ): array {
			++$resolved;
			return array( 'resolved' => $resolved );
		};
		$initial    = $this->cache->remember( $key, $resolver );
		$cached     = $this->cache->remember( $key, $resolver );
		$generation = (string) get_option( 'citeoryx_rest_response_cache_version' );

		$this->assertSame( $initial, $cached );
		$this->assertSame( 1, $resolved );

		RestResponseCache::invalidate();
		RestResponseCache::invalidate();
		RestResponseCache::flush();
		$invalidated_generation = (string) get_option( 'citeoryx_rest_response_cache_version' );

		$this->assertNotSame( $generation, $invalidated_generation );
		RestResponseCache::flush();
		$this->assertSame( $invalidated_generation, (string) get_option( 'citeoryx_rest_response_cache_version' ) );
		$this->assertSame( array( 'resolved' => 2 ), $this->cache->remember( $key, $resolver ) );
	}

	public function test_aggregate_repositories_invalidate_cached_payloads(): void {
		$content_repo = new ContentRepository();
		$item         = $this->create_content_item( 'cache-source' );
		$item->id     = $content_repo->save( $item );
		RestResponseCache::flush();

		$this->assert_write_invalidates(
			'content',
			static function () use ( $content_repo ): void {
				$content_repo->save( self::create_content_item( 'cache-content-write' ) );
			}
		);

		$this->assert_write_invalidates(
			'issue',
			static function () use ( $item ): void {
				$issue             = new Issue();
				$issue->content_id = $item->id;
				$issue->issue_code = 'CX_CACHE_INVALIDATION';
				$issue->category   = 'content';
				$issue->title      = 'Cache invalidation issue';
				( new IssueRepository() )->save( $issue );
			}
		);

		$this->assert_write_invalidates(
			'scan',
			static function (): void {
				$run            = new ScanRun();
				$run->scan_type = 'incremental';
				$run->status    = 'queued';
				( new ScanRunRepository() )->create( $run );
			}
		);

		$this->assert_write_invalidates(
			'metrics',
			static function () use ( $item ): void {
				( new MetricsRepository() )->save(
					(int) $item->id,
					gmdate( 'Y-m-d' ),
					'gsc',
					array(
						'impressions' => 10,
						'clicks'      => 2,
					)
				);
			}
		);
	}

	/**
	 * Assert that one write changes the cache generation before the next read.
	 *
	 * @param string   $name Cache-key suffix.
	 * @param callable $write Write operation.
	 * @return void
	 */
	private function assert_write_invalidates( string $name, callable $write ): void {
		$key      = $this->cache_key( $name );
		$resolved = 0;
		$resolver = static function () use ( &$resolved ): array {
			++$resolved;
			return array( 'resolved' => $resolved );
		};

		$this->cache->remember( $key, $resolver );
		$write();

		$this->assertSame( array( 'resolved' => 2 ), $this->cache->remember( $key, $resolver ) );
	}

	/**
	 * Create a valid standalone content item.
	 *
	 * @param string $slug Unique path suffix.
	 * @return ContentItem
	 */
	private static function create_content_item( string $slug ): ContentItem {
		$item                = new ContentItem();
		$item->object_type   = 'post';
		$item->post_type     = 'post';
		$item->canonical_url = home_url( '/' . $slug );
		$item->url_hash      = md5( $item->canonical_url );
		$item->status        = 'healthy';
		return $item;
	}

	private function cache_key( string $suffix ): string {
		return 'unit_' . md5( $this->getName() . ':' . $suffix );
	}
}
