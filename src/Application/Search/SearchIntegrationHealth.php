<?php
/**
 * Search integration health store.
 *
 * @package Citeoryx\Application\Search
 */

namespace Citeoryx\Application\Search;

/**
 * Persists provider request health and exposes alert-worthy failures.
 */
class SearchIntegrationHealth {

	const OPTION          = 'citeoryx_search_integration_health';
	const ALERT_THRESHOLD = 2;

	/**
	 * Return one provider state.
	 *
	 * @param string $provider Provider key.
	 * @return array<string, mixed>
	 */
	public function get( string $provider ): array {
		$states = $this->all();
		return $states[ sanitize_key( $provider ) ] ?? $this->default_state();
	}

	/**
	 * Return all persisted provider states.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array {
		$states = get_option( self::OPTION, array() );
		return is_array( $states ) ? $states : array();
	}

	/**
	 * Record a successful provider request.
	 *
	 * @param string $provider Provider key.
	 * @param string $message Optional status message.
	 * @return array<string, mixed>
	 */
	public function record_success( string $provider, string $message = 'Connection is healthy.' ): array {
		$now   = gmdate( 'c' );
		$state = array(
			'status'               => 'healthy',
			'message'              => sanitize_text_field( $message ),
			'checked_at'           => $now,
			'consecutive_failures' => 0,
			'last_success_at'      => $now,
		);

		$this->save( $provider, $state );
		return $state;
	}

	/**
	 * Record a failed provider request.
	 *
	 * @param string $provider Provider key.
	 * @param string $message Safe error summary.
	 * @return array<string, mixed>
	 */
	public function record_failure( string $provider, string $message ): array {
		$previous = $this->get( $provider );
		$state    = array(
			'status'               => 'error',
			'message'              => sanitize_text_field( $message ),
			'checked_at'           => gmdate( 'c' ),
			'consecutive_failures' => (int) $previous['consecutive_failures'] + 1,
			'last_success_at'      => $previous['last_success_at'],
		);

		$this->save( $provider, $state );
		return $state;
	}

	/**
	 * Remove stale health when an integration is disconnected or reconfigured.
	 *
	 * @param string $provider Provider key.
	 * @return void
	 */
	public function clear( string $provider ): void {
		$provider = sanitize_key( $provider );
		$states   = $this->all();
		unset( $states[ $provider ] );
		update_option( self::OPTION, $states, false );
	}

	/**
	 * Return provider states that crossed the consecutive failure threshold.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_alerts(): array {
		$labels = array(
			'google_search_console' => 'Google Search Console',
			'bing_webmaster_tools'  => 'Bing Webmaster Tools',
		);
		$alerts = array();
		foreach ( $this->all() as $provider => $state ) {
			if ( (int) ( $state['consecutive_failures'] ?? 0 ) < self::ALERT_THRESHOLD ) {
				continue;
			}
			$state['provider'] = $provider;
			$state['label']    = $labels[ $provider ] ?? $provider;
			$alerts[]          = $state;
		}
		return $alerts;
	}

	/**
	 * Persist one provider state.
	 *
	 * @param string               $provider Provider key.
	 * @param array<string, mixed> $state State.
	 * @return void
	 */
	private function save( string $provider, array $state ): void {
		$states                              = $this->all();
		$states[ sanitize_key( $provider ) ] = $state;
		update_option( self::OPTION, $states, false );
	}

	/**
	 * Return a stable unknown-state contract.
	 *
	 * @return array<string, mixed>
	 */
	private function default_state(): array {
		return array(
			'status'               => 'unknown',
			'message'              => null,
			'checked_at'           => null,
			'consecutive_failures' => 0,
			'last_success_at'      => null,
		);
	}
}
