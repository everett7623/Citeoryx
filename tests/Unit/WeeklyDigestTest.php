<?php
/**
 * Weekly digest tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Application\Notifications\WeeklyDigest;
use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Domain\Issue\IssueRepository;
use WP_UnitTestCase;

/**
 * Tests weekly email scheduling and idempotency.
 */
class WeeklyDigestTest extends WP_UnitTestCase {

	private WeeklyDigest $digest;
	private array $messages = array();
	private string $original_admin_email;

	public function setUp(): void {
		parent::setUp();
		$this->original_admin_email = (string) get_option( 'admin_email' );
		update_option( 'admin_email', 'owner@example.com' );
		$this->digest = new WeeklyDigest( new ContentRepository(), new IssueRepository() );
		add_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10, 2 );
	}

	public function tearDown(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10 );
		wp_clear_scheduled_hook( WeeklyDigest::HOOK );
		delete_option( 'citeoryx_settings' );
		delete_option( 'citeoryx_last_weekly_digest_period' );
		delete_option( 'citeoryx_notification_status' );
		update_option( 'admin_email', $this->original_admin_email );
		parent::tearDown();
	}

	public function test_disabled_digest_does_not_send(): void {
		$result = $this->digest->send_weekly();

		$this->assertSame( 'skipped', $result['status'] );
		$this->assertCount( 0, $this->messages );
	}

	public function test_weekly_digest_is_idempotent_per_iso_week(): void {
		update_option(
			'citeoryx_settings',
			array(
				'weekly_digest_enabled' => true,
				'notification_email'    => 'owner@example.com',
			)
		);

		$first  = $this->digest->send_weekly();
		$second = $this->digest->send_weekly();

		$this->assertSame( 'sent', $first['status'] );
		$this->assertSame( 'skipped', $second['status'] );
		$this->assertCount( 1, $this->messages );
		$this->assertMatchesRegularExpression( '/^\d{4}-W\d{2}$/', get_option( 'citeoryx_last_weekly_digest_period' ) );
	}

	public function test_test_email_uses_configured_recipient(): void {
		$result = $this->digest->send_test( 'reports@example.com' );

		$this->assertSame( 'sent', $result['status'] );
		$this->assertSame( 'reports@example.com', $this->messages[0]['to'] );
		$this->assertStringContainsString( 'Citeoryx', $this->messages[0]['subject'] );
	}

	public function test_next_digest_uses_site_monday_morning(): void {
		$this->digest->ensure_scheduled();
		$timestamp = wp_next_scheduled( WeeklyDigest::HOOK );

		$this->assertIsInt( $timestamp );
		$this->assertGreaterThan( time(), $timestamp );
		$this->assertSame( '1 09:00', wp_date( 'N H:i', $timestamp, wp_timezone() ) );
	}

	/**
	 * Capture email attributes without calling an external mail transport.
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
