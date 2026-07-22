<?php
/**
 * Publishing and review calendar service.
 *
 * @package Citeoryx\Application\Planning
 */

namespace Citeoryx\Application\Planning;

use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Domain\Planning\CalendarRepository;

/**
 * Builds a site-timezone-aware planning calendar.
 */
class PlanningCalendar {

	private CalendarRepository $calendar_repo;
	private ContentRepository $content_repo;

	public function __construct( CalendarRepository $calendar_repo, ContentRepository $content_repo ) {
		$this->calendar_repo = $calendar_repo;
		$this->content_repo  = $content_repo;
	}

	/**
	 * Build the bounded planning calendar.
	 *
	 * @param int                     $horizon_days Future schedule horizon.
	 * @param int                     $limit Per-section item limit.
	 * @param \DateTimeImmutable|null $now Explicit site-timezone clock for tests.
	 * @return array<string, mixed>
	 */
	public function get( int $horizon_days = 90, int $limit = 50, ?\DateTimeImmutable $now = null ): array {
		$now              = $now ?: current_datetime();
		$horizon_days     = max( 7, min( 365, $horizon_days ) );
		$limit            = max( 1, min( 100, $limit ) );
		$cycle_days       = $this->review_cycle_days();
		$cutoff           = $now->sub( new \DateInterval( 'P' . $cycle_days . 'D' ) );
		$scheduled        = $this->calendar_repo->find_scheduled(
			$this->post_types(),
			$now->format( 'Y-m-d H:i:s' ),
			$now->add( new \DateInterval( 'P' . $horizon_days . 'D' ) )->format( 'Y-m-d H:i:s' ),
			$limit
		);
		$reviews          = $this->calendar_repo->find_due_reviews( $cutoff->format( 'Y-m-d H:i:s' ), $limit );
		$reviews['items'] = array_map(
			fn ( array $row ): array => $this->format_review( $row, $cycle_days, $now ),
			$reviews['items']
		);

		return array(
			'as_of'             => $now->format( DATE_ATOM ),
			'timezone'          => $now->getTimezone()->getName(),
			'horizon_days'      => $horizon_days,
			'review_cycle_days' => $cycle_days,
			'scheduled'         => $scheduled,
			'overdue_reviews'   => $reviews,
		);
	}

	/**
	 * Mark an existing content item as reviewed in site time.
	 *
	 * @param int                     $content_id Content item ID.
	 * @param \DateTimeImmutable|null $now Explicit clock for tests.
	 * @return array<string, mixed>|null
	 */
	public function complete_review( int $content_id, ?\DateTimeImmutable $now = null ): ?array {
		$item = $this->content_repo->find( $content_id );
		if ( ! $item ) {
			return null;
		}

		$now                    = $now ?: current_datetime();
		$item->last_reviewed_at = $now->format( 'Y-m-d H:i:s' );
		$this->content_repo->save( $item );
		return array(
			'content_id'  => $content_id,
			'reviewed_at' => $now->format( DATE_ATOM ),
		);
	}

	/**
	 * Add due-date metadata without extra queries.
	 *
	 * @param array<string, mixed> $row Review row.
	 * @param int                  $cycle_days Review cycle.
	 * @param \DateTimeImmutable   $now Current site time.
	 * @return array<string, mixed>
	 */
	private function format_review( array $row, int $cycle_days, \DateTimeImmutable $now ): array {
		$reference = \DateTimeImmutable::createFromFormat(
			'Y-m-d H:i:s',
			$row['review_reference_at'],
			$now->getTimezone()
		);
		$due_at    = $reference ? $reference->add( new \DateInterval( 'P' . $cycle_days . 'D' ) ) : null;

		return array(
			'content_id'          => $row['id'],
			'object_id'           => $row['object_id'],
			'object_type'         => $row['object_type'],
			'post_type'           => $row['post_type'],
			'title'               => $row['post_title'] ?: $row['canonical_url'],
			'url'                 => $row['canonical_url'],
			'edit_url'            => 'post' === $row['object_type'] && $row['object_id']
				? admin_url( 'post.php?post=' . $row['object_id'] . '&action=edit' )
				: null,
			'status'              => $row['status'],
			'health_score'        => $row['health_score'],
			'review_reference_at' => $reference ? $reference->format( DATE_ATOM ) : null,
			'due_at'              => $due_at ? $due_at->format( DATE_ATOM ) : null,
			'overdue_days'        => $due_at ? (int) $due_at->diff( $now )->format( '%a' ) : null,
		);
	}

	/**
	 * Read a validated review cycle from the site profile.
	 *
	 * @return int
	 */
	private function review_cycle_days(): int {
		$profile = get_option( 'citeoryx_site_profile', array() );
		$cycle   = is_array( $profile ) ? (int) ( $profile['review_cycle_days'] ?? 90 ) : 90;
		return in_array( $cycle, array( 30, 90, 180, 365 ), true ) ? $cycle : 90;
	}

	/**
	 * Resolve configured post types against the current site.
	 *
	 * @return array<string>
	 */
	private function post_types(): array {
		$allowed = get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'names'
		);
		$allowed = array_values( array_diff( $allowed, array( 'attachment' ) ) );
		$profile = get_option( 'citeoryx_site_profile', array() );
		$chosen  = is_array( $profile ) ? (array) ( $profile['core_content_types'] ?? array() ) : array();
		$chosen  = array_values( array_intersect( $chosen, $allowed ) );
		return $chosen ?: $allowed;
	}
}
