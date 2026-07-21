<?php
/**
 * Weekly email digest.
 *
 * @package Citeoryx\Application\Notifications
 */

namespace Citeoryx\Application\Notifications;

use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Domain\Issue\IssueRepository;

/**
 * Sends a bounded weekly content health summary.
 */
class WeeklyDigest {

	public const HOOK = 'citeoryx_weekly_digest';

	private ContentRepository $content_repo;
	private IssueRepository $issue_repo;

	public function __construct( ContentRepository $content_repo, IssueRepository $issue_repo ) {
		$this->content_repo = $content_repo;
		$this->issue_repo   = $issue_repo;
	}

	/**
	 * Ensure the next single event exists.
	 *
	 * @return void
	 */
	public function ensure_scheduled(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( $this->next_digest_timestamp(), self::HOOK );
		}
	}

	/**
	 * Send the digest and schedule the following occurrence.
	 *
	 * @return void
	 */
	public function send_scheduled(): void {
		try {
			$this->send_weekly();
		} finally {
			$this->ensure_scheduled();
		}
	}

	/**
	 * Send one weekly digest unless this ISO week was already sent.
	 *
	 * @return array{status:string,message:string,attempted_at:string|null,recipient:string}
	 */
	public function send_weekly(): array {
		$settings = get_option( 'citeoryx_settings', array() );
		if ( empty( $settings['weekly_digest_enabled'] ) ) {
			return $this->record_status( 'skipped', __( '每周周报未启用。', 'citeoryx' ) );
		}

		$period = $this->period_key();
		if ( get_option( 'citeoryx_last_weekly_digest_period', '' ) === $period ) {
			return $this->status_result( 'skipped', __( '本周期周报已发送。', 'citeoryx' ) );
		}

		return $this->send( $period );
	}

	/**
	 * Send a test email without changing the weekly period marker.
	 *
	 * @return array{status:string,message:string,attempted_at:string|null,recipient:string}
	 */
	public function send_test( ?string $recipient = null ): array {
		return $this->send( null, true, $recipient );
	}

	/**
	 * Read the last notification state with a stable shape.
	 *
	 * @return array{status:string,message:string,attempted_at:string|null,recipient:string}
	 */
	public function get_status(): array {
		$stored = get_option( 'citeoryx_notification_status', array() );
		if ( ! is_array( $stored ) ) {
			return $this->status_result( 'never', '' );
		}

		$status = isset( $stored['status'] ) && in_array( $stored['status'], array( 'never', 'sent', 'failed', 'skipped' ), true )
			? $stored['status']
			: 'never';
		return array(
			'status'       => $status,
			'message'      => isset( $stored['message'] ) && is_scalar( $stored['message'] ) ? sanitize_text_field( (string) $stored['message'] ) : '',
			'attempted_at' => isset( $stored['attempted_at'] ) && is_string( $stored['attempted_at'] ) ? $stored['attempted_at'] : null,
			'recipient'    => isset( $stored['recipient'] ) && is_scalar( $stored['recipient'] ) ? sanitize_email( (string) $stored['recipient'] ) : '',
		);
	}

	/**
	 * Send an email and persist the result.
	 *
	 * @param string|null $period Weekly period marker.
	 * @param bool        $test Whether this is a test message.
	 * @param string|null $recipient_override Optional test recipient.
	 * @return array{status:string,message:string,attempted_at:string|null,recipient:string}
	 */
	private function send( ?string $period = null, bool $test = false, ?string $recipient_override = null ): array {
		$settings  = get_option( 'citeoryx_settings', array() );
		$recipient = sanitize_email(
			(string) ( $recipient_override ?? $settings['notification_email'] ?? get_option( 'admin_email' ) )
		);
		if ( ! is_email( $recipient ) ) {
			return $this->record_status( 'failed', __( '通知邮箱地址无效。', 'citeoryx' ), $recipient );
		}

		$site_name = wp_strip_all_tags( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		$subject   = sprintf(
			/* translators: %s: site name. */
			__( '[Citeoryx] %s 内容周报', 'citeoryx' ),
			$site_name
		);
		if ( $test ) {
			$subject = '[Citeoryx] ' . __( '测试邮件', 'citeoryx' ) . ' - ' . $site_name;
		}

		try {
			$accepted = wp_mail(
				$recipient,
				$subject,
				$this->build_message( $site_name, $test ),
				array( 'Content-Type: text/plain; charset=UTF-8' )
			);
		} catch ( \Throwable ) {
			return $this->record_status( 'failed', __( '邮件发送过程发生异常。', 'citeoryx' ), $recipient );
		}

		if ( ! $accepted ) {
			return $this->record_status( 'failed', __( 'WordPress 未接受这封邮件。', 'citeoryx' ), $recipient );
		}

		if ( $period ) {
			update_option( 'citeoryx_last_weekly_digest_period', $period, false );
		}
		return $this->record_status( 'sent', __( 'WordPress 已接受邮件发送请求。', 'citeoryx' ), $recipient );
	}

	/**
	 * Build a plain-text message from bounded local aggregates.
	 *
	 * @param string $site_name Site name.
	 * @param bool   $test Whether this is a test message.
	 * @return string
	 */
	private function build_message( string $site_name, bool $test ): string {
		$content         = $this->content_repo->report_summary();
		$issues          = $this->issue_repo->list( array( 'status' => 'open' ), 1, 5 );
		$severity_counts = $this->issue_repo->count_open_by( 'severity' );
		$high_count      = 0;
		foreach ( $severity_counts as $row ) {
			if ( 'high' === $row['label'] ) {
				$high_count = $row['count'];
				break;
			}
		}

		$lines = array(
			$test ? __( '这是一封 Citeoryx 测试邮件。', 'citeoryx' ) : __( 'Citeoryx 每周内容健康周报', 'citeoryx' ),
			$site_name,
			__( '统计时间：', 'citeoryx' ) . current_datetime()->format( 'Y-m-d H:i:s T' ),
			'',
			__( '内容资产：', 'citeoryx' ) . $content['total'],
			__( '平均健康分：', 'citeoryx' ) . ( null === $content['average_health_score'] ? '—' : $content['average_health_score'] ),
			__( '平均 AI 准备度：', 'citeoryx' ) . ( null === $content['average_ai_readiness_score'] ? '—' : $content['average_ai_readiness_score'] ),
			__( '待处理问题：', 'citeoryx' ) . $issues['total'],
			__( '高严重度问题：', 'citeoryx' ) . $high_count,
		);

		if ( ! empty( $issues['items'] ) ) {
			$lines[] = '';
			$lines[] = __( '优先问题：', 'citeoryx' );
			foreach ( $issues['items'] as $issue ) {
				$lines[] = '- ' . wp_strip_all_tags( $issue->title );
			}
		}

		$lines[] = '';
		$lines[] = admin_url( 'admin.php?page=citeoryx-dashboard#/reports' );
		return implode( "\n", $lines );
	}

	/**
	 * Persist a notification result.
	 *
	 * @param string $status Status.
	 * @param string $message Message.
	 * @param string $recipient Recipient.
	 * @return array{status:string,message:string,attempted_at:string|null,recipient:string}
	 */
	private function record_status( string $status, string $message, string $recipient = '' ): array {
		$result = $this->status_result( $status, $message, $recipient );
		update_option( 'citeoryx_notification_status', $result, false );
		return $result;
	}

	/**
	 * Build a notification result.
	 *
	 * @param string $status Status.
	 * @param string $message Message.
	 * @param string $recipient Recipient.
	 * @return array{status:string,message:string,attempted_at:string|null,recipient:string}
	 */
	private function status_result( string $status, string $message, string $recipient = '' ): array {
		return array(
			'status'       => $status,
			'message'      => $message,
			'attempted_at' => 'never' === $status ? null : current_datetime()->format( DATE_ATOM ),
			'recipient'    => sanitize_email( $recipient ),
		);
	}

	private function period_key(): string {
		return current_datetime()->format( 'o-\\WW' );
	}

	private function next_digest_timestamp(): int {
		$now               = current_datetime();
		$target            = $now->setTime( 9, 0 );
		$days_until_monday = ( 8 - (int) $now->format( 'N' ) ) % 7;
		if ( $days_until_monday > 0 ) {
			$target = $target->modify( "+{$days_until_monday} days" );
		}
		if ( $target <= $now ) {
			$target = $target->modify( '+7 days' );
		}
		return $target->getTimestamp();
	}
}
