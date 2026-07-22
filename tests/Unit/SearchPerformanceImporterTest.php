<?php
/**
 * Search performance importer tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Application\Search\SearchIntegrationHealth;
use Citeoryx\Application\Search\SearchPerformanceImporter;
use Citeoryx\Domain\Content\ContentItem;
use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Domain\Metrics\MetricsRepository;
use Citeoryx\Integrations\SearchConsole\BingWebmasterTools;
use Citeoryx\Integrations\SearchConsole\GoogleSearchConsole;
use WP_UnitTestCase;

/**
 * Tests importer orchestration across providers and repositories.
 */
class SearchPerformanceImporterTest extends WP_UnitTestCase {

	/**
	 * Remove health state written by the importer.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( SearchIntegrationHealth::OPTION );
		parent::tearDown();
	}

	/**
	 * Google dimension rows should be persisted beside daily aggregates.
	 *
	 * @return void
	 */
	public function test_importer_requests_and_saves_dimension_rows(): void {
		$item                = new ContentItem();
		$item->id            = 7;
		$item->canonical_url = home_url( '/example' );
		$content_repo        = new class( $item ) extends ContentRepository {
			/** @var ContentItem */
			private ContentItem $item;

			/** @param ContentItem $item Content fixture. */
			public function __construct( ContentItem $item ) {
				$this->item = $item;
			}

			/** @return array<int, ContentItem> */
			public function list_after_id( int $after_id = 0, int $limit = 100 ): array {
				return array( $this->item );
			}
		};
		$metrics_repo        = new class() extends MetricsRepository {
			/** @var array<string, mixed> */
			public array $daily = array();
			/** @var array<string, mixed> */
			public array $dimensions = array();

			/** @return int */
			public function save( int $content_id, string $date, string $source, array $metrics ): int {
				$this->daily = compact( 'content_id', 'date', 'source', 'metrics' );
				return 1;
			}

			/** @return int */
			public function save_query_pages( int $content_id, string $source, string $period_start, string $period_end, array $rows ): int {
				$this->dimensions = compact( 'content_id', 'source', 'period_start', 'period_end', 'rows' );
				return count( $rows );
			}
		};
		$google              = new class() extends GoogleSearchConsole {
			/** @var array<string, mixed> */
			public array $options = array();

			/** Test double constructor. */
			public function __construct() {
			}

			/** @return bool */
			public function is_connected(): bool {
				return true;
			}

			/** @return array<int, array<string, mixed>> */
			public function get_queries_for_url( string $url, string $start_date, string $end_date, array $options = array() ): array {
				$this->options = $options;
				return array(
					array(
						'query'       => 'example query',
						'metric_date' => '2026-07-19',
						'country'     => 'usa',
						'device'      => 'mobile',
						'impressions' => 40,
						'clicks'      => 4,
						'position'    => 3.5,
					),
				);
			}
		};
		$bing                = new class() extends BingWebmasterTools {
			/** Test double constructor. */
			public function __construct() {
			}

			/** @return bool */
			public function is_connected(): bool {
				return false;
			}
		};

		$importer = new SearchPerformanceImporter(
			$content_repo,
			$metrics_repo,
			$google,
			$bing,
			new SearchIntegrationHealth()
		);
		$result   = $importer->import_batch( 0, 20, '2026-07-20' );

		$this->assertSame( array( 'query', 'country', 'device' ), $google->options['dimensions'] );
		$this->assertSame( 1, $result['imported'] );
		$this->assertSame( 1, $result['dimension_rows'] );
		$this->assertSame( 'example query', $metrics_repo->dimensions['rows'][0]['query'] );
		$this->assertSame( '2026-07-19', $metrics_repo->daily['date'] );
		$this->assertSame( '2026-07-19', $metrics_repo->dimensions['period_start'] );
		$this->assertSame( 40.0, $metrics_repo->daily['metrics']['impressions'] );
	}
}
