<?php
/**
 * Search integration health tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Application\Search\SearchIntegrationHealth;
use WP_UnitTestCase;

/**
 * Tests persisted provider health and alert thresholds.
 */
class SearchIntegrationHealthTest extends WP_UnitTestCase {

	/**
	 * Clear health state after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( SearchIntegrationHealth::OPTION );
		parent::tearDown();
	}

	/**
	 * A transient failure should be visible without creating a global alert.
	 *
	 * @return void
	 */
	public function test_alert_requires_two_consecutive_failures(): void {
		$health = new SearchIntegrationHealth();

		$first = $health->record_failure( 'google_search_console', 'Remote request failed.' );
		$this->assertSame( 'error', $first['status'] );
		$this->assertSame( 1, $first['consecutive_failures'] );
		$this->assertSame( array(), $health->get_alerts() );

		$second = $health->record_failure( 'google_search_console', 'Remote request failed again.' );
		$alerts = $health->get_alerts();
		$this->assertSame( 2, $second['consecutive_failures'] );
		$this->assertCount( 1, $alerts );
		$this->assertSame( 'Google Search Console', $alerts[0]['label'] );
	}

	/**
	 * A successful request resets the failure streak and records recovery.
	 *
	 * @return void
	 */
	public function test_success_resets_failure_state(): void {
		$health = new SearchIntegrationHealth();
		$health->record_failure( 'bing_webmaster_tools', 'Timeout.' );
		$state = $health->record_success( 'bing_webmaster_tools', 'Connection is healthy.' );

		$this->assertSame( 'healthy', $state['status'] );
		$this->assertSame( 0, $state['consecutive_failures'] );
		$this->assertSame( $state['checked_at'], $state['last_success_at'] );
		$this->assertSame( array(), $health->get_alerts() );
	}

	/**
	 * Clearing a disconnected provider removes stale health information.
	 *
	 * @return void
	 */
	public function test_clear_restores_unknown_state(): void {
		$health = new SearchIntegrationHealth();
		$health->record_failure( 'google_search_console', 'Expired token.' );
		$health->clear( 'google_search_console' );

		$state = $health->get( 'google_search_console' );
		$this->assertSame( 'unknown', $state['status'] );
		$this->assertNull( $state['message'] );
		$this->assertSame( 0, $state['consecutive_failures'] );
	}
}
