<?php
/**
 * Google OAuth helper for Search Console.
 *
 * @package Citeoryx\Integrations\SearchConsole
 */

namespace Citeoryx\Integrations\SearchConsole;

use Citeoryx\Infrastructure\Encryption\KeyStore;

/**
 * Handles Google OAuth 2.0 flow for Search Console access.
 */
class GoogleOAuth {

	const OPTION_CLIENT   = 'citeoryx_gsc_client';
	const TOKEN_KEY       = 'gsc_tokens';
	const CLIENT_SECRET_KEY = 'gsc_client_secret';
	const TRANSIENT_STATE = 'citeoryx_gsc_oauth_state';
	const REDIRECT_ACTION = 'citeoryx_gsc_callback';

	const AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
	const TOKEN_URL = 'https://oauth2.googleapis.com/token';
	const SCOPE     = 'https://www.googleapis.com/auth/webmasters.readonly';

	/**
	 * Get stored OAuth tokens.
	 *
	 * @return array<string, mixed>
	 */
	public function get_tokens(): array {
		$stored = ( new KeyStore() )->get( self::TOKEN_KEY );
		$tokens = is_string( $stored ) ? json_decode( $stored, true ) : array();
		return is_array( $tokens ) ? $tokens : array();
	}

	/**
	 * Check if a valid access token exists.
	 *
	 * @return bool
	 */
	public function is_connected(): bool {
		$tokens = $this->get_tokens();
		return ! empty( $tokens['access_token'] );
	}

	/**
	 * Get the client credentials.
	 *
	 * @return array{client_id: string, client_secret: string}
	 */
	public function get_client(): array {
		$stored = (array) get_option( self::OPTION_CLIENT, array() );
		return array(
			'client_id'     => $stored['client_id'] ?? '',
			'client_secret' => ( new KeyStore() )->get( self::CLIENT_SECRET_KEY ) ?? '',
		);
	}

	/**
	 * Save client credentials.
	 *
	 * @param string $client_id     Client ID.
	 * @param string $client_secret Client secret.
	 * @return void
	 */
	public function save_client( string $client_id, string $client_secret ): void {
		update_option( self::OPTION_CLIENT, array( 'client_id' => sanitize_text_field( $client_id ) ) );
		( new KeyStore() )->set( self::CLIENT_SECRET_KEY, $client_secret );
	}

	/**
	 * Build the authorization URL.
	 *
	 * @return string|null
	 */
	public function get_auth_url(): ?string {
		$client = $this->get_client();
		if ( empty( $client['client_id'] ) ) {
			return null;
		}

		$state = wp_generate_uuid4();
		set_transient( self::TRANSIENT_STATE, $state, 300 );

		return add_query_arg(
			array(
				'client_id'     => $client['client_id'],
				'redirect_uri'  => $this->get_redirect_uri(),
				'response_type' => 'code',
				'scope'         => self::SCOPE,
				'access_type'   => 'offline',
				'prompt'        => 'consent',
				'state'         => $state,
			),
			self::AUTH_URL
		);
	}

	/**
	 * Handle the OAuth callback.
	 *
	 * @param string $code  Authorization code.
	 * @param string $state State parameter.
	 * @return bool True on success.
	 */
	public function handle_callback( string $code, string $state ): bool {
		$saved_state = get_transient( self::TRANSIENT_STATE );
		delete_transient( self::TRANSIENT_STATE );

		if ( ! $saved_state || ! hash_equals( $saved_state, $state ) ) {
			return false;
		}

		$client = $this->get_client();
		if ( empty( $client['client_id'] ) || empty( $client['client_secret'] ) ) {
			return false;
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'body' => array(
					'code'          => $code,
					'client_id'     => $client['client_id'],
					'client_secret' => $client['client_secret'],
					'redirect_uri'  => $this->get_redirect_uri(),
					'grant_type'    => 'authorization_code',
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			return false;
		}

		$tokens = array(
			'access_token'  => $body['access_token'],
			'refresh_token' => $body['refresh_token'] ?? '',
			'expires_at'    => time() + ( (int) ( $body['expires_in'] ?? 3600 ) ),
		);

		( new KeyStore() )->set( self::TOKEN_KEY, wp_json_encode( $tokens ) );
		return true;
	}

	/**
	 * Refresh the access token if expired.
	 *
	 * @return bool True if token is valid after refresh.
	 */
	public function ensure_valid_token(): bool {
		$tokens = $this->get_tokens();
		if ( empty( $tokens['access_token'] ) ) {
			return false;
		}

		if ( ! empty( $tokens['expires_at'] ) && time() < ( $tokens['expires_at'] - 60 ) ) {
			return true;
		}

		if ( empty( $tokens['refresh_token'] ) ) {
			return false;
		}

		$client = $this->get_client();
		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'body' => array(
					'refresh_token' => $tokens['refresh_token'],
					'client_id'     => $client['client_id'],
					'client_secret' => $client['client_secret'],
					'grant_type'    => 'refresh_token',
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			return false;
		}

		$tokens['access_token'] = $body['access_token'];
		$tokens['expires_at']   = time() + ( (int) ( $body['expires_in'] ?? 3600 ) );
		( new KeyStore() )->set( self::TOKEN_KEY, wp_json_encode( $tokens ) );

		return true;
	}

	/**
	 * Get the valid access token.
	 *
	 * @return string|null
	 */
	public function get_access_token(): ?string {
		if ( ! $this->ensure_valid_token() ) {
			return null;
		}
		$tokens = $this->get_tokens();
		return $tokens['access_token'] ?? null;
	}

	/**
	 * Disconnect / delete stored tokens.
	 *
	 * @return void
	 */
	public function disconnect(): void {
		( new KeyStore() )->delete( self::TOKEN_KEY );
	}

	/**
	 * Get the OAuth redirect URI.
	 *
	 * @return string
	 */
	public function get_redirect_uri(): string {
		return admin_url( 'admin.php?page=citeoryx&action=' . self::REDIRECT_ACTION );
	}
}
