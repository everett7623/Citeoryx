<?php
/**
 * Search integration health state.
 *
 * @package Citeoryx\Application\Search
 */

namespace Citeoryx\Application\Search;

/**
 * Persists connection and import health for external search providers.
 */
class SearchIntegrationHealth {

	const OPTION = 'citeoryx_search_integration_health';

	/**
	 * Supported provider keys.
	 *
	 * @var array<int, string>
	 */
	private const PROVIDERS = array(
		'google_search_console',
		'bing_webmaster_tools',
	);

	/**
	 * Return the normalized health state for one provider.
	 *
	 * @param string $provider Provider key.
	 * @return array<string, mixed>
	 */
	public function get( string $provider ): array {
		$health = $this->all();
		return $health[ $provider ] ?? $this->default_state();
	}

	/**
	 * Return all provider health states.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$health = array();

		foreach ( self::PROVIDERS as $provider ) {
			$health[ $provider ] = $this->normalize( $stored[ $provider ] ?? array() );
		}

		return $health;
	}

	/**
	 * Record a successful provider request.
	 *
	 * @param string      $provider Provider key.
	 * @param string|null $message Optional status message.
	 * @return array<string, mixed>
	 */
	public function record_success( string $provider, ?string $message = null ): array {
		if ( ! in_array( $provider, self::PROVIDERS, true ) ) {
			return $this->default_state();
		}

		$now   = current_time( 'mysql' );
		$state = $this->get( $provider );
		$state = array_merge(
			$state,
			array(
				'status'              => 'healthy',
				'message'             => $message ? sanitize_text_field( $message ) : null,
				'checked_at'          => $now,
				'consecutive_failures' => 0,
				'last_success_at'     => $now,
			)
		);
		$this->save( $provider, $state );

		return $state;
	}

	/**
	 * Record a failed provider request.
	 *
	 * @param string $provider Provider key.
	 * @param string $message Error message.
	 * @return array<string, mixed>
	 */
	public function record_failure( string $provider, string $message ): array {
		if ( ! in_array( $provider, self::PROVIDERS, true ) ) {
			return $this->default_state();
		}

		$state = $this->get( $provider );
		$state = array_merge(
			$state,
			array(
				'status'              => 'error',
				'message'             => $this->truncate( $message ),
				'checked_at'          => current_time( 'mysql' ),
				'consecutive_failures' => (int) $state['consecutive_failures'] + 1,
			)
		);
		$this->save( $provider, $state );

		return $state;
	}

	/**
	 * Clear stale state after a provider is disconnected.
	 *
	 * @param string $provider Provider key.
	 * @return void
	 */
	public function clear( string $provider ): void {
		if ( ! in_array( $provider, self::PROVIDERS, true ) ) {
			return;
		}

		$this->save( $provider, $this->default_state() );
	}

	/**
	 * Return failures that are ready for an admin notice.
	 *
	 * A single transient network error is kept in the integration UI but does
	 * not create a persistent dashboard notice until it repeats.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_alerts(): array {
		$alerts = array();
		foreach ( $this->all() as $provider => $state ) {
			if ( 'error' !== $state['status'] || (int) $state['consecutive_failures'] < 2 ) {
				continue;
			}

			$alerts[] = array_merge(
				$state,
				array(
					'provider' => $provider,
					'label'    => $this->label( $provider ),
				)
			);
		}

		return $alerts;
	}

	/**
	 * Build the default state.
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

	/**
	 * Normalize persisted state from older or incomplete options.
	 *
	 * @param mixed $state Stored state.
	 * @return array<string, mixed>
	 */
	private function normalize( $state ): array {
		$state   = is_array( $state ) ? $state : array();
		$default = $this->default_state();
		$state   = array_merge( $default, $state );
		if ( ! in_array( $state['status'], array( 'unknown', 'healthy', 'error' ), true ) ) {
			$state['status'] = 'unknown';
		}
		$state['consecutive_failures'] = max( 0, (int) $state['consecutive_failures'] );
		$state['message']              = $state['message'] ? $this->truncate( (string) $state['message'] ) : null;
		$state['checked_at']           = $state['checked_at'] ? sanitize_text_field( (string) $state['checked_at'] ) : null;
		$state['last_success_at'] = $state['last_success_at']
			? sanitize_text_field( (string) $state['last_success_at'] )
			: null;
		return $state;
	}

	/**
	 * Save one provider state in the shared option.
	 *
	 * @param string               $provider Provider key.
	 * @param array<string, mixed> $state State.
	 * @return void
	 */
	private function save( string $provider, array $state ): void {
		$all            = get_option( self::OPTION, array() );
		$all            = is_array( $all ) ? $all : array();
		$all[ $provider ] = $this->normalize( $state );
		update_option( self::OPTION, $all, false );
	}

	/**
	 * Limit remote error text before storing it.
	 *
	 * @param string $message Error text.
	 * @return string
	 */
	private function truncate( string $message ): string {
		$message = sanitize_text_field( $message );
		return function_exists( 'mb_substr' )
			? mb_substr( $message, 0, 240 )
			: substr( $message, 0, 240 );
	}

	/**
	 * Return a human-readable provider label.
	 *
	 * @param string $provider Provider key.
	 * @return string
	 */
	private function label( string $provider ): string {
		$labels = array(
			'google_search_console' => 'Google Search Console',
			'bing_webmaster_tools'  => 'Bing Webmaster Tools',
		);
		return $labels[ $provider ] ?? $provider;
	}
}
