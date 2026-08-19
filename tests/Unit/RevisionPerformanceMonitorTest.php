<?php
/**
 * Revision post-publish performance monitor tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Application\Optimize\RevisionDraftService;
use Citeoryx\Application\Optimize\RevisionPerformanceMonitor;
use Citeoryx\Domain\Content\ContentItem;
use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Domain\Metrics\MetricsRepository;
use WP_UnitTestCase;

/**
 * Ensures only an explicitly verified proposal can have a comparable window.
 */
class RevisionPerformanceMonitorTest extends WP_UnitTestCase {

	private ContentRepository $content_repo;
	private RevisionDraftService $revision_drafts;
	private MetricsRepository $metrics;
	private RevisionPerformanceMonitor $monitor;

	public function setUp(): void {
		parent::setUp();
		$this->content_repo    = new ContentRepository();
		$this->revision_drafts = new RevisionDraftService( $this->content_repo );
		$this->metrics         = new MetricsRepository();
		$this->monitor         = new RevisionPerformanceMonitor( $this->revision_drafts, $this->metrics );
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $wpdb->prefix . CITEORYX_TABLE_METRICS_DAILY ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		parent::tearDown();
	}

	/**
	 * @return void
	 */
	public function test_only_verified_proposals_receive_provider_separated_comparisons(): void {
		$item     = $this->create_content();
		$post     = get_post( $item->object_id );
		$proposal = $this->revision_drafts->create(
			$item->id,
			array(
				'title'             => 'Updated title',
				'content'           => '<p>Updated body</p>',
				'excerpt'           => 'Updated excerpt',
				'base_content_hash' => $this->hash( $post ),
			)
		);
		$this->assertIsArray( $proposal );
		$this->assertFalse( $this->monitor->get_performance( $item->id )['available'] );

		$revision = wp_get_post_revision( $proposal['id'] );
		wp_update_post(
			array(
				'ID'           => $post->ID,
				'post_title'   => $revision->post_title,
				'post_content' => $revision->post_content,
				'post_excerpt' => $revision->post_excerpt,
				'post_status'  => 'publish',
			)
		);

		$published    = current_datetime()->modify( '-8 days' );
		$published_at = $published->format( 'Y-m-d H:i:s' );
		$this->set_post_modified( $post->ID, $published_at );
		$updated               = get_post( $post->ID );
		$item->content_hash    = hash( 'sha256', $updated->post_content );
		$item->last_scanned_at = current_time( 'mysql' );
		$this->content_repo->save( $item );
		$this->revision_drafts->record_verified_scan( $item->id );

		$published_day = substr( $published_at, 0, 10 );
		$baseline_day  = $published->modify( '-1 day' )->format( 'Y-m-d' );
		$this->metrics->save(
			$item->id,
			$baseline_day,
			'google_search_console',
			array(
				'impressions'  => 100,
				'clicks'       => 10,
				'position_avg' => 4,
			)
		);
		$this->metrics->save(
			$item->id,
			$published_day,
			'google_search_console',
			array(
				'impressions'  => 200,
				'clicks'       => 30,
				'position_avg' => 2,
			)
		);
		$this->metrics->save(
			$item->id,
			$baseline_day,
			'bing_webmaster_tools',
			array(
				'impressions'  => 40,
				'clicks'       => 4,
				'position_avg' => 7,
			)
		);
		$this->metrics->save(
			$item->id,
			$published_day,
			'bing_webmaster_tools',
			array(
				'impressions'  => 60,
				'clicks'       => 3,
				'position_avg' => 8,
			)
		);

		$performance = $this->monitor->get_performance( $item->id );
		$seven_days  = $performance['windows'][0];
		$sources     = array_column( $seven_days['sources'], null, 'source' );

		$this->assertTrue( $performance['available'] );
		$this->assertNotEmpty( $performance['published_at'] );
		$this->assertNotEmpty( $performance['verified_at'] );
		$this->assertSame( 'ready', $seven_days['state'] );
		$this->assertSame( 7, $seven_days['elapsed_days'] );
		$this->assertSame( 20.0, $sources['google_search_console']['delta']['clicks'] );
		$this->assertSame( -2.0, $sources['google_search_console']['delta']['position_avg'] );
		$this->assertSame( -1.0, $sources['bing_webmaster_tools']['delta']['clicks'] );
		$this->assertSame( 1.0, $sources['bing_webmaster_tools']['delta']['position_avg'] );
	}

	/**
	 * @return ContentItem
	 */
	private function create_content(): ContentItem {
		$post_id             = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_title'   => 'Original title',
				'post_content' => '<p>Original body</p>',
				'post_excerpt' => 'Original excerpt',
			)
		);
		$item                = new ContentItem();
		$item->object_id     = $post_id;
		$item->object_type   = 'post';
		$item->post_type     = 'post';
		$item->canonical_url = get_permalink( $post_id );
		$item->url_hash      = md5( $item->canonical_url );
		$item->id            = $this->content_repo->save( $item );
		return $item;
	}

	/**
	 * @param int    $post_id Post ID.
	 * @param string $modified Local MySQL datetime.
	 * @return void
	 */
	private function set_post_modified( int $post_id, string $modified ): void {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->posts,
			array(
				'post_modified'     => $modified,
				'post_modified_gmt' => get_gmt_from_date( $modified ),
			),
			array( 'ID' => $post_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		clean_post_cache( $post_id );
	}

	/**
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	private function hash( \WP_Post $post ): string {
		return hash(
			'sha256',
			maybe_serialize( array( $post->post_title, $post->post_content, $post->post_excerpt ) )
		);
	}
}
