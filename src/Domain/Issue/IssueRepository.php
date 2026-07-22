<?php
/**
 * Issue repository.
 *
 * @package Citeoryx\Domain\Issue
 */

namespace Citeoryx\Domain\Issue;

/**
 * Repository for issues.
 */
class IssueRepository {

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . CITEORYX_TABLE_ISSUES;
	}

	/**
	 * Find by ID.
	 *
	 * @param int $id Issue ID.
	 * @return Issue|null
	 */
	public function find( int $id ): ?Issue {
		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$this->table(),
				$id
			)
		);

		if ( ! $row ) {
			return null;
		}

		return Issue::from_row( $row );
	}

	/**
	 * Save issue.
	 *
	 * @param Issue $issue Issue.
	 * @return int
	 */
	public function save( Issue $issue ): int {
		global $wpdb;

		$now = current_time( 'mysql' );
		if ( ! $issue->id && $issue->content_id && $issue->issue_code ) {
			$existing = $this->find_latest_by_content_and_code( $issue->content_id, $issue->issue_code );
			if ( $existing ) {
				$issue->id               = $existing->id;
				$issue->first_seen_at    = $existing->first_seen_at;
				$issue->assigned_user_id = $existing->assigned_user_id;
				$issue->ignored_until    = $existing->ignored_until;
				$issue->status           = $this->status_for_refresh( $existing );
			}
		}

		$data = array(
			'content_id'       => $issue->content_id,
			'issue_code'       => $issue->issue_code,
			'category'         => $issue->category,
			'severity'         => $issue->severity,
			'confidence'       => $issue->confidence,
			'status'           => $issue->status,
			'impact_score'     => $issue->impact_score,
			'effort_score'     => $issue->effort_score,
			'priority_score'   => $issue->priority_score,
			'title'            => $issue->title,
			'evidence_json'    => $issue->evidence ? wp_json_encode( $issue->evidence ) : null,
			'recommendation'   => $issue->recommendation,
			'last_seen_at'     => $now,
			'resolved_at'      => $issue->resolved_at,
			'ignored_until'    => $issue->ignored_until,
			'assigned_user_id' => $issue->assigned_user_id,
		);

		if ( $issue->id ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$this->table(),
				$data,
				array( 'id' => $issue->id ),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%d' ),
				array( '%d' )
			);
			return $issue->id;
		}

		$data['first_seen_at'] = $now;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			$data,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Find the latest occurrence of an issue for a content item.
	 *
	 * @param int    $content_id Content ID.
	 * @param string $issue_code Issue code.
	 * @return Issue|null
	 */
	private function find_latest_by_content_and_code( int $content_id, string $issue_code ): ?Issue {
		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT * FROM %i WHERE content_id = %d AND issue_code = %s ORDER BY id DESC LIMIT 1',
				$this->table(),
				$content_id,
				$issue_code
			)
		);

		return $row ? Issue::from_row( $row ) : null;
	}

	/**
	 * Preserve workflow state while refreshing an issue from analysis.
	 *
	 * @param Issue $existing Existing issue.
	 * @return string
	 */
	private function status_for_refresh( Issue $existing ): string {
		if ( 'ignored' === $existing->status && ( ! $existing->ignored_until || strtotime( $existing->ignored_until ) > time() ) ) {
			return 'ignored';
		}

		return in_array( $existing->status, array( 'open', 'in_progress' ), true ) ? $existing->status : 'open';
	}

	/**
	 * List issues.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @param int                  $page Page.
	 * @param int                  $per_page Items per page.
	 * @return array{items: array<Issue>, total: int}
	 */
	public function list( array $filters = array(), int $page = 1, int $per_page = 20 ): array {
		global $wpdb;

		$from  = $this->table() . ' AS issue';
		$where = array( '1=1' );
		$args  = array();

		if ( array_key_exists( 'author_id', $filters ) ) {
			$content_table = $wpdb->prefix . CITEORYX_TABLE_CONTENT_ITEMS;
			$from         .= " INNER JOIN {$content_table} AS content ON content.id = issue.content_id";
			$from         .= " INNER JOIN {$wpdb->posts} AS posts ON posts.ID = content.object_id AND content.object_type = 'post'";
			$where[]       = 'posts.post_author = %d';
			$args[]        = max( 0, (int) $filters['author_id'] );
		}

		if ( ! empty( $filters['status'] ) ) {
			$where[] = 'issue.status = %s';
			$args[]  = $filters['status'];
		}
		if ( ! empty( $filters['category'] ) ) {
			$where[] = 'issue.category = %s';
			$args[]  = $filters['category'];
		}
		if ( ! empty( $filters['severity'] ) ) {
			$where[] = 'issue.severity = %s';
			$args[]  = $filters['severity'];
		}
		if ( ! empty( $filters['content_id'] ) ) {
			$where[] = 'issue.content_id = %d';
			$args[]  = $filters['content_id'];
		}

		$where_sql = implode( ' AND ', $where );
		$offset    = ( $page - 1 ) * $per_page;

		// Query fragments contain only internal table names and fixed filter clauses.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$count_sql = "SELECT COUNT(*) FROM {$from} WHERE {$where_sql}";
		if ( ! empty( $args ) ) {
			$count_sql = $wpdb->prepare( $count_sql, ...$args );
		}
		$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT issue.* FROM {$from} WHERE {$where_sql} ORDER BY issue.priority_score DESC, issue.last_seen_at DESC LIMIT %d OFFSET %d",
				...array_merge( $args, array( $per_page, $offset ) )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		$items = array();
		foreach ( $rows as $row ) {
			$items[] = Issue::from_row( $row );
		}

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Resolve issues by code and content.
	 *
	 * @param int    $content_id Content ID.
	 * @param string $issue_code Issue code.
	 * @return void
	 */
	public function resolve_by_code( int $content_id, string $issue_code ): void {
		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array(
				'status'      => 'resolved',
				'resolved_at' => current_time( 'mysql' ),
			),
			array(
				'content_id' => $content_id,
				'issue_code' => $issue_code,
				'status'     => 'open',
			),
			array( '%s', '%s' ),
			array( '%d', '%s', '%s' )
		);
	}

	/**
	 * Count open issues grouped by a supported reporting dimension.
	 *
	 * @param string $dimension Dimension name.
	 * @return array<int, array{label:string,count:int}>
	 */
	public function count_open_by( string $dimension ): array {
		global $wpdb;

		$columns = array( 'severity', 'category' );
		if ( ! in_array( $dimension, $columns, true ) ) {
			return array();
		}

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT %i AS label, COUNT(*) AS count FROM %i WHERE status = 'open' GROUP BY %i ORDER BY count DESC, %i ASC",
				$dimension,
				$this->table(),
				$dimension,
				$dimension
			),
			ARRAY_A
		);

		return array_map(
			static fn ( $row ) => array(
				'label' => (string) $row['label'],
				'count' => (int) $row['count'],
			),
			$rows ?: array()
		);
	}

	/**
	 * Find a bounded set of unresolved high-severity issues for alerts.
	 *
	 * @param int $limit Maximum rows.
	 * @return array<int, array{id:int,severity:string,title:string,priority_score:float|null,canonical_url:string}>
	 */
	public function list_alertable( int $limit = 100 ): array {
		global $wpdb;

		$limit         = min( 100, max( 1, $limit ) );
		$content_table = $wpdb->prefix . CITEORYX_TABLE_CONTENT_ITEMS;
		$rows          = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT issue.id, issue.severity, issue.title, issue.priority_score,
					COALESCE(content.canonical_url, '') AS canonical_url
				FROM %i AS issue
				LEFT JOIN %i AS content ON content.id = issue.content_id
				WHERE issue.status IN ('open', 'in_progress')
					AND issue.severity IN ('critical', 'high')
				ORDER BY CASE issue.severity WHEN 'critical' THEN 0 ELSE 1 END,
					issue.priority_score DESC, issue.id DESC
				LIMIT %d",
				$this->table(),
				$content_table,
				$limit
			),
			ARRAY_A
		);

		return array_map(
			static fn ( array $row ) => array(
				'id'             => (int) $row['id'],
				'severity'       => (string) $row['severity'],
				'title'          => (string) $row['title'],
				'priority_score' => null !== $row['priority_score'] ? (float) $row['priority_score'] : null,
				'canonical_url'  => (string) $row['canonical_url'],
			),
			$rows ?: array()
		);
	}
}
