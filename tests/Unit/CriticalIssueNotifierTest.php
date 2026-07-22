<?php
/**
 * Critical issue notifier tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Application\Notifications\CriticalIssueNotifier;
use Citeoryx\Domain\Issue\IssueRepository;
use WP_UnitTestCase;

/**
 * Tests bounded alert delivery and deduplication.
 */
class CriticalIssueNotifierTest extends WP_UnitTestCase {

	private array $messages = array();
	private string $original_admin_email;

	public function setUp(): void {
		parent::setUp();
		$this->original_admin_email = (string) get_option( 'admin_email' );
		update_option( 'admin_email', 'owner@example.com' );
		add_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10, 2 );
	}

	public function tearDown(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10 );
		delete_option( 'citeoryx_settings' );
		delete_option( 'citeoryx_critical_alert_fingerprint' );
		delete_option( 'citeoryx_critical_alert_status' );
		update_option( 'admin_email', $this->original_admin_email );
		parent::tearDown();
	}

	public function test_disabled_alert_does_not_query_or_send(): void {
		$notifier = new CriticalIssueNotifier( $this->repository( array() ) );

		$result = $notifier->send();

		$this->assertSame( 'skipped', $result['status'] );
		$this->assertCount( 0, $this->messages );
	}

	public function test_same_issue_set_is_sent_only_once(): void {
		update_option(
			'citeoryx_settings',
			array(
				'critical_alerts_enabled' => true,
				'notification_email'      => 'owner@example.com',
			)
		);
		$issues   = array(
			array(
				'id'             => 17,
				'severity'       => 'high',
				'title'          => 'Broken canonical target',
				'priority_score' => 8.5,
				'canonical_url'  => home_url( '/article' ),
			),
		);
		$notifier = new CriticalIssueNotifier( $this->repository( $issues ) );

		$first  = $notifier->send();
		$second = $notifier->send();

		$this->assertSame( 'sent', $first['status'] );
		$this->assertSame( 'skipped', $second['status'] );
		$this->assertSame( 1, $first['issue_count'] );
		$this->assertCount( 1, $this->messages );
		$this->assertStringContainsString( 'Broken canonical target', $this->messages[0]['message'] );
	}

	public function test_empty_issue_set_clears_previous_fingerprint(): void {
		update_option( 'citeoryx_settings', array( 'critical_alerts_enabled' => true ) );
		update_option( 'citeoryx_critical_alert_fingerprint', 'old-fingerprint' );
		$notifier = new CriticalIssueNotifier( $this->repository( array() ) );

		$result = $notifier->send();

		$this->assertSame( 'skipped', $result['status'] );
		$this->assertFalse( get_option( 'citeoryx_critical_alert_fingerprint', false ) );
	}

	public function test_scan_hook_contains_repository_failure(): void {
		update_option( 'citeoryx_settings', array( 'critical_alerts_enabled' => true ) );
		$repository = new class() extends IssueRepository {
			public function list_alertable( int $limit = 100 ): array {
				throw new \RuntimeException( 'Database unavailable.' );
			}
		};
		$notifier   = new CriticalIssueNotifier( $repository );

		$result = $notifier->send_after_scan( 9 );

		$this->assertSame( 'failed', $result['status'] );
		$this->assertCount( 0, $this->messages );
	}

	private function repository( array $issues ): IssueRepository {
		return new class( $issues ) extends IssueRepository {
			private array $issues;

			public function __construct( array $issues ) {
				$this->issues = $issues;
			}

			public function list_alertable( int $limit = 100 ): array {
				return array_slice( $this->issues, 0, $limit );
			}
		};
	}

	/**
	 * Capture email attributes without an external mail transport.
	 *
	 * @param mixed                $return Short-circuit value.
	 * @param array<string, mixed> $attributes Mail attributes.
	 * @return bool
	 */
	public function capture_mail( $return, array $attributes ): bool {
		$this->messages[] = $attributes;
		return true;
	}
}
