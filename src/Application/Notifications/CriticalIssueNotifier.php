<?php
/**
 * Critical issue email alerts.
 *
 * @package Citeoryx\Application\Notifications
 */

namespace Citeoryx\Application\Notifications;

use Citeoryx\Domain\Issue\IssueRepository;
use Citeoryx\Infrastructure\Logging\Logger;

/**
 * Sends one deduplicated summary after a completed scan.
 */
class CriticalIssueNotifier {

	private const FINGERPRINT_OPTION = 'citeoryx_critical_alert_fingerprint';
	private const STATUS_OPTION      = 'citeoryx_critical_alert_status';
	private const QUERY_LIMIT        = 100;
	private const DISPLAY_LIMIT      = 10;

	private IssueRepository $issue_repo;

	public function __construct( IssueRepository $issue_repo ) {
		$this->issue_repo = $issue_repo;
	}

	/**
	 * Safe scan-completion hook boundary.
	 *
	 * @param int $run_id Scan run ID.
	 * @return array{status:string,message:string,attempted_at:string|null,recipient:string,issue_count:int}
	 */
	public function send_after_scan( int $run_id = 0 ): array {
		unset( $run_id );
		try {
			return $this->send();
		} catch ( \Throwable $error ) {
			Logger::error( 'Critical alert failed', array( 'error' => $error->getMessage() ) );
			return $this->record_status( 'failed', __( '严重问题通知处理失败。', 'citeoryx' ) );
		}
	}

	/**
	 * Evaluate current issues and send when their identity set changes.
	 *
	 * @return array{status:string,message:string,attempted_at:string|null,recipient:string,issue_count:int}
	 */
	public function send(): array {
		$settings = get_option( 'citeoryx_settings', array() );
		if ( empty( $settings['critical_alerts_enabled'] ) ) {
			return $this->record_status( 'skipped', __( '严重问题通知未启用。', 'citeoryx' ) );
		}

		$issues = $this->issue_repo->list_alertable( self::QUERY_LIMIT );
		if ( empty( $issues ) ) {
			delete_option( self::FINGERPRINT_OPTION );
			return $this->record_status( 'skipped', __( '当前没有待处理的严重问题。', 'citeoryx' ) );
		}

		$fingerprint = $this->fingerprint( $issues );
		$stored      = get_option( self::FINGERPRINT_OPTION, '' );
		$previous    = is_string( $stored ) ? $stored : '';
		if ( hash_equals( $previous, $fingerprint ) ) {
			return $this->record_status( 'skipped', __( '严重问题集合未变化，已跳过重复通知。', 'citeoryx' ), '', count( $issues ) );
		}

		$recipient = sanitize_email( (string) ( $settings['notification_email'] ?? get_option( 'admin_email' ) ) );
		if ( ! is_email( $recipient ) ) {
			return $this->record_status( 'failed', __( '通知邮箱地址无效。', 'citeoryx' ), $recipient, count( $issues ) );
		}

		$site_name = wp_strip_all_tags( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		$accepted  = wp_mail(
			$recipient,
			/* translators: %s: site name. */
			sprintf( __( '[Citeoryx] %s 发现严重内容问题', 'citeoryx' ), $site_name ),
			$this->build_message( $site_name, $issues ),
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);
		if ( ! $accepted ) {
			return $this->record_status( 'failed', __( 'WordPress 未接受这封严重问题通知。', 'citeoryx' ), $recipient, count( $issues ) );
		}

		update_option( self::FINGERPRINT_OPTION, $fingerprint, false );
		return $this->record_status( 'sent', __( 'WordPress 已接受严重问题通知。', 'citeoryx' ), $recipient, count( $issues ) );
	}

	/**
	 * Read the last alert state with a stable shape.
	 *
	 * @return array{status:string,message:string,attempted_at:string|null,recipient:string,issue_count:int}
	 */
	public function get_status(): array {
		$stored = get_option( self::STATUS_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			return $this->status_result( 'never', '' );
		}

		$status = isset( $stored['status'] ) && in_array( $stored['status'], array( 'never', 'sent', 'failed', 'skipped' ), true )
			? $stored['status'] : 'never';
		return array(
			'status'       => $status,
			'message'      => isset( $stored['message'] ) && is_scalar( $stored['message'] ) ? sanitize_text_field( (string) $stored['message'] ) : '',
			'attempted_at' => isset( $stored['attempted_at'] ) && is_string( $stored['attempted_at'] ) ? $stored['attempted_at'] : null,
			'recipient'    => isset( $stored['recipient'] ) && is_scalar( $stored['recipient'] ) ? sanitize_email( (string) $stored['recipient'] ) : '',
			'issue_count'  => isset( $stored['issue_count'] ) ? max( 0, (int) $stored['issue_count'] ) : 0,
		);
	}

	private function fingerprint( array $issues ): string {
		$identities = array_map( static fn ( array $issue ) => $issue['id'] . ':' . $issue['severity'], $issues );
		sort( $identities, SORT_STRING );
		return hash( 'sha256', implode( '|', $identities ) );
	}

	private function build_message( string $site_name, array $issues ): string {
		$lines = array(
			__( 'Citeoryx 严重问题通知', 'citeoryx' ),
			$site_name,
			__( '扫描时间：', 'citeoryx' ) . current_datetime()->format( 'Y-m-d H:i:s T' ),
			__( '待处理严重问题：', 'citeoryx' ) . count( $issues ),
			'',
		);
		foreach ( array_slice( $issues, 0, self::DISPLAY_LIMIT ) as $issue ) {
			$lines[] = sprintf( '- [%s] %s', strtoupper( $issue['severity'] ), wp_strip_all_tags( $issue['title'] ) );
			if ( $issue['canonical_url'] ) {
				$lines[] = '  ' . esc_url_raw( $issue['canonical_url'] );
			}
		}
		if ( count( $issues ) >= self::QUERY_LIMIT ) {
			$lines[] = __( '仅统计优先级最高的 100 条，请前往后台查看完整列表。', 'citeoryx' );
		} elseif ( count( $issues ) > self::DISPLAY_LIMIT ) {
			/* translators: %d: number of additional serious issues. */
			$lines[] = sprintf( __( '邮件仅展示前 10 条，另有 %d 条请前往后台查看。', 'citeoryx' ), count( $issues ) - self::DISPLAY_LIMIT );
		}
		$lines[] = '';
		$lines[] = admin_url( 'admin.php?page=citeoryx-dashboard#/issues?severity=high' );
		return implode( "\n", $lines );
	}

	private function record_status( string $status, string $message, string $recipient = '', int $issue_count = 0 ): array {
		$result = $this->status_result( $status, $message, $recipient, $issue_count );
		update_option( self::STATUS_OPTION, $result, false );
		return $result;
	}

	private function status_result( string $status, string $message, string $recipient = '', int $issue_count = 0 ): array {
		return array(
			'status'       => $status,
			'message'      => $message,
			'attempted_at' => 'never' === $status ? null : current_datetime()->format( DATE_ATOM ),
			'recipient'    => sanitize_email( $recipient ),
			'issue_count'  => max( 0, $issue_count ),
		);
	}
}
