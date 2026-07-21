<?php
/**
 * Issue model.
 *
 * @package Citeoryx\Domain\Issue
 */

namespace Citeoryx\Domain\Issue;

/**
 * Issue value object.
 */
class Issue {

	public ?int $id                = null;
	public ?int $content_id        = null;
	public string $issue_code      = '';
	public string $category        = '';
	public string $severity        = 'low';
	public string $confidence      = 'low';
	public string $status          = 'open';
	public ?float $impact_score    = null;
	public ?float $effort_score    = null;
	public ?float $priority_score  = null;
	public string $title           = '';
	public array $evidence         = array();
	public ?string $recommendation = null;
	public string $first_seen_at   = '';
	public string $last_seen_at    = '';
	public ?string $resolved_at    = null;
	public ?string $ignored_until  = null;
	public ?int $assigned_user_id  = null;

	/**
	 * Create from database row.
	 *
	 * @param object $row Database row.
	 * @return self
	 */
	public static function from_row( object $row ): self {
		$issue                   = new self();
		$issue->id               = (int) $row->id;
		$issue->content_id       = $row->content_id ? (int) $row->content_id : null;
		$issue->issue_code       = $row->issue_code;
		$issue->category         = $row->category;
		$issue->severity         = $row->severity;
		$issue->confidence       = $row->confidence;
		$issue->status           = $row->status;
		$issue->impact_score     = null !== $row->impact_score ? (float) $row->impact_score : null;
		$issue->effort_score     = null !== $row->effort_score ? (float) $row->effort_score : null;
		$issue->priority_score   = null !== $row->priority_score ? (float) $row->priority_score : null;
		$issue->title            = $row->title;
		$issue->evidence         = ! empty( $row->evidence_json ) ? json_decode( $row->evidence_json, true ) ?: array() : array();
		$issue->recommendation   = $row->recommendation;
		$issue->first_seen_at    = $row->first_seen_at;
		$issue->last_seen_at     = $row->last_seen_at;
		$issue->resolved_at      = $row->resolved_at;
		$issue->ignored_until    = $row->ignored_until;
		$issue->assigned_user_id = $row->assigned_user_id ? (int) $row->assigned_user_id : null;

		return $issue;
	}

	/**
	 * Convert to array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'               => $this->id,
			'content_id'       => $this->content_id,
			'issue_code'       => $this->issue_code,
			'category'         => $this->category,
			'severity'         => $this->severity,
			'confidence'       => $this->confidence,
			'status'           => $this->status,
			'impact_score'     => $this->impact_score,
			'effort_score'     => $this->effort_score,
			'priority_score'   => $this->priority_score,
			'title'            => $this->title,
			'evidence'         => $this->evidence,
			'recommendation'   => $this->recommendation,
			'first_seen_at'    => $this->first_seen_at,
			'last_seen_at'     => $this->last_seen_at,
			'resolved_at'      => $this->resolved_at,
			'ignored_until'    => $this->ignored_until,
			'assigned_user_id' => $this->assigned_user_id,
		);
	}
}
