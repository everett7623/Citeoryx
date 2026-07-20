<?php
/**
 * Encrypted key storage.
 *
 * @package Citeoryx\Infrastructure\Encryption
 */

namespace Citeoryx\Infrastructure\Encryption;

/**
 * Stores and retrieves encrypted API keys / tokens.
 */
class KeyStore {

	/**
	 * Get key.
	 *
	 * @param string $name Key name.
	 * @return string|null
	 */
	public function get( string $name ): ?string {
		$constant = 'CITEORYX_' . strtoupper( $name );
		if ( defined( $constant ) ) {
			return constant( $constant );
		}

		$value = getenv( $constant );
		if ( false !== $value ) {
			return $value;
		}

		$encrypted = get_option( 'citeoryx_key_' . $name, null );
		if ( ! $encrypted ) {
			return null;
		}

		$decrypted = $this->decrypt( $encrypted );
		return is_string( $decrypted ) ? $decrypted : null;
	}

	/**
	 * Set key.
	 *
	 * @param string $name Key name.
	 * @param string $value Key value.
	 * @return bool
	 */
	public function set( string $name, string $value ): bool {
		$encrypted = $this->encrypt( $value );
		return update_option( 'citeoryx_key_' . $name, $encrypted, true );
	}

	/**
	 * Delete key.
	 *
	 * @param string $name Key name.
	 * @return bool
	 */
	public function delete( string $name ): bool {
		return delete_option( 'citeoryx_key_' . $name );
	}

	/**
	 * Encrypt value.
	 *
	 * @param string $value Plain text.
	 * @return string
	 */
	private function encrypt( string $value ): string {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			return base64_encode( $value ); // Fallback, not for production.
		}

		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$key   = $this->encryption_key();
		return base64_encode( $nonce . sodium_crypto_secretbox( $value, $nonce, $key ) );
	}

	/**
	 * Decrypt value.
	 *
	 * @param string $value Encrypted text.
	 * @return string|false
	 */
	private function decrypt( string $value ) {
		if ( ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
			return base64_decode( $value, true );
		}

		$decoded = base64_decode( $value, true );
		if ( false === $decoded ) {
			return false;
		}

		$nonce_size = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
		$nonce      = substr( $decoded, 0, $nonce_size );
		$ciphertext = substr( $decoded, $nonce_size );
		$key        = $this->encryption_key();

		return sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );
	}

	/**
	 * Derive encryption key from WordPress salts.
	 *
	 * @return string
	 */
	private function encryption_key(): string {
		$secret = defined( 'AUTH_KEY' ) ? AUTH_KEY : wp_rand();
		return hash( 'sha256', $secret . 'citeoryx', true );
	}
}
