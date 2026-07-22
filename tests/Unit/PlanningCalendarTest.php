<?php
/**
 * Planning calendar service tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Application\Planning\PlanningCalendar;
use Citeoryx\Domain\Content\ContentItem;
use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Domain\Planning\CalendarRepository;
use WP_UnitTestCase;

/**
 * Tests timezone-aware publishing and review planning.
 */
class PlanningCalendarTest extends WP_UnitTestCase {

	/**
	 * Clean content fixtures.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $wpdb->prefix . CITEORYX_TABLE_CONTENT_ITEMS ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		parent::tearDown();
	}

	/**
	 * Calendar should apply the profile cycle using the explicit site clock.
	 *
	 * @return void
	 */
	public function test_calendar_calculates_due_dates_in_site_timezone(): void {
		update_option( 'citeoryx_site_profile', array( 'review_cycle_days' => 30 ) );
		$repository = new class() extends CalendarRepository {
			public string $cutoff = '';

			public function find_scheduled( array $post_types, string $start, string $end, int $limit = 50 ): array {
				return array(
					'items'        => array(),
					'data_limited' => false,
				);
			}

			public function find_due_reviews( string $cutoff, int $limit = 50 ): array {
				$this->cutoff = $cutoff;
				return array(
					'items'        => array(
						array(
							'id'                  => 7,
							'object_id'           => 11,
							'object_type'         => 'post',
							'post_type'           => 'post',
							'canonical_url'       => 'https://example.com/review',
							'status'              => 'healthy',
							'health_score'        => 80.0,
							'post_title'          => 'Review me',
							'review_reference_at' => '2026-06-01 10:00:00',
						),
					),
					'data_limited' => false,
				);
			}
		};
		$now        = new \DateTimeImmutable( '2026-07-22 10:00:00', new \DateTimeZone( 'Asia/Shanghai' ) );
		$result     = ( new PlanningCalendar( $repository, new ContentRepository() ) )->get( 90, 50, $now );

		$this->assertSame( '2026-06-22 10:00:00', $repository->cutoff );
		$this->assertSame( 'Asia/Shanghai', $result['timezone'] );
		$this->assertSame( 30, $result['review_cycle_days'] );
		$this->assertSame( '2026-07-01T10:00:00+08:00', $result['overdue_reviews']['items'][0]['due_at'] );
		$this->assertSame( 21, $result['overdue_reviews']['items'][0]['overdue_days'] );
	}

	/**
	 * Completing a review should persist local time without changing status.
	 *
	 * @return void
	 */
	public function test_complete_review_persists_review_time(): void {
		$item                = new ContentItem();
		$item->canonical_url = 'https://example.com/complete-review';
		$item->url_hash      = md5( $item->canonical_url );
		$item->status        = 'stale';
		$repository          = new ContentRepository();
		$item->id            = $repository->save( $item );
		$now                 = new \DateTimeImmutable( '2026-07-22 15:30:00', new \DateTimeZone( 'Asia/Shanghai' ) );
		$calendar            = new PlanningCalendar( new CalendarRepository(), $repository );

		$result = $calendar->complete_review( $item->id, $now );
		$saved  = $repository->find( $item->id );

		$this->assertSame( '2026-07-22T15:30:00+08:00', $result['reviewed_at'] );
		$this->assertSame( '2026-07-22 15:30:00', $saved->last_reviewed_at );
		$this->assertSame( 'stale', $saved->status );
	}
}
