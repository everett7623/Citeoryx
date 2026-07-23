<?php
/**
 * Search performance scheduler tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Application\Analyze\IssueEngine;
use Citeoryx\Application\Scan\ContentScanner;
use Citeoryx\Application\Scan\LinkChecker;
use Citeoryx\Application\Search\SearchPerformanceImporter;
use Citeoryx\Domain\Scan\ScanRunRepository;
use Citeoryx\Infrastructure\Queue\Scheduler;
use WP_UnitTestCase;

/**
 * Tests bounded search import scheduling.
 */
class SearchSchedulerTest extends WP_UnitTestCase {

	/**
	 * Clear search import events after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		wp_clear_scheduled_hook( 'citeoryx_daily_search_performance_import' );
		wp_clear_scheduled_hook( 'citeoryx_import_search_performance_batch' );
		parent::tearDown();
	}

	/**
	 * The recurring trigger should be created only when absent.
	 *
	 * @return void
	 */
	public function test_ensure_schedule_creates_daily_event(): void {
		$scheduler = $this->scheduler(
			array(
				'complete' => true,
				'last_id'  => 0,
			)
		);

		$scheduler->ensure_search_performance_schedule();
		$first = wp_next_scheduled( 'citeoryx_daily_search_performance_import' );
		$scheduler->ensure_search_performance_schedule();

		$this->assertIsInt( $first );
		$this->assertSame( $first, wp_next_scheduled( 'citeoryx_daily_search_performance_import' ) );
	}

	/**
	 * An incomplete batch must continue from the returned immutable ID cursor.
	 *
	 * @return void
	 */
	public function test_incomplete_batch_schedules_next_cursor(): void {
		$scheduler = $this->scheduler(
			array(
				'complete' => false,
				'last_id'  => 42,
			)
		);

		$scheduler->import_search_performance_batch( 0 );

		$this->assertNotFalse(
			wp_next_scheduled( 'citeoryx_import_search_performance_batch', array( 'after_id' => 42 ) )
		);
	}

	/**
	 * Build the scheduler with isolated collaborators.
	 *
	 * @param array<string, mixed> $result Import result fixture.
	 * @return Scheduler
	 */
	private function scheduler( array $result ): Scheduler {
		$scanner  = new class() extends ContentScanner {
			public function __construct() {}
		};
		$engine   = new class() extends IssueEngine {
			public function __construct() {}
		};
		$checker  = new class() extends LinkChecker {
			public function __construct() {}
		};
		$importer = new class( $result ) extends SearchPerformanceImporter {
			private array $result;

			public function __construct( array $result ) {
				$this->result = $result;
			}

			public function import_batch( int $after_id = 0, int $limit = 20, ?string $date = null ): array {
				return $this->result;
			}
		};

		return new Scheduler( $scanner, $engine, $checker, new ScanRunRepository(), $importer );
	}
}
