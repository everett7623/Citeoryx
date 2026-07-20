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
				"SELECT * FROM {$this->table()} WHERE id = %d",
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
			'resolved_at'     => $issue->resolved_at,
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
	 * List issues.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @param int                  $page Page.
	 * @param int                  $per_page Items per page.
	 * @return array{items: array<Issue>, total: int}
	 */
	public function list( array $filters = array(), int $page = 1, int $per_page = 20 ): array {
		global $wpdb;

		$where = array( '1=1' );
		$args  = array();

		if ( ! empty( $filters['status'] ) ) {
			$where[] = 'status = %s';
			$args[]  = $filters['status'];
		}
		if ( ! empty( $filters['category'] ) ) {
			$where[] = 'category = %s';
			$args[]  = $filters['category'];
		}
		if ( ! empty( $filters['severity'] ) ) {
			$where[] = 'severity = %s';
			$args[]  = $filters['severity'];
		}
		if ( ! empty( $filters['content_id'] ) ) {
			$where[] = 'content_id = %d';
			$args[]  = $filters['content_id'];
		}

		$where_sql = implode( ' AND ', $where );
		$offset    = ( $page - 1 ) * $per_page;

		$total = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table()} WHERE {$where_sql}",
				...$args
			)
		);

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE {$where_sql} ORDER BY priority_score DESC, last_seen_at DESC LIMIT %d OFFSET %d",
				...array_merge( $args, array( $per_page, $offset ) )
			)
		);

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
}
