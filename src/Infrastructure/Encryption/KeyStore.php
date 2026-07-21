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
		try {
			$encrypted = $this->encrypt( $value );
		} catch ( \Throwable $exception ) {
			return false;
		}
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
		$key = $this->encryption_key();
		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encodes binary ciphertext for storage.
			return base64_encode( $nonce . sodium_crypto_secretbox( $value, $nonce, $key ) );
		}

		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv         = random_bytes( openssl_cipher_iv_length( 'aes-256-cbc' ) );
			$ciphertext = openssl_encrypt( $value, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
			if ( false !== $ciphertext ) {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encodes binary ciphertext for storage.
				return 'openssl:' . base64_encode( $iv . $ciphertext );
			}
		}

		throw new \RuntimeException( 'No supported encryption extension is available.' );
	}

	/**
	 * Decrypt value.
	 *
	 * @param string $value Encrypted text.
	 * @return string|false
	 */
	private function decrypt( string $value ) {
		if ( 0 === strpos( $value, 'openssl:' ) ) {
			if ( ! function_exists( 'openssl_decrypt' ) ) {
				return false;
			}
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decodes stored ciphertext.
			$decoded = base64_decode( substr( $value, 8 ), true );
			$iv_size = openssl_cipher_iv_length( 'aes-256-cbc' );
			if ( false === $decoded || strlen( $decoded ) <= $iv_size ) {
				return false;
			}
			return openssl_decrypt( substr( $decoded, $iv_size ), 'aes-256-cbc', $this->encryption_key(), OPENSSL_RAW_DATA, substr( $decoded, 0, $iv_size ) );
		}

		if ( ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
			return false;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decodes stored ciphertext.
		$decoded = base64_decode( $value, true );
		if ( false === $decoded ) {
			return false;
		}

		$nonce_size = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
		if ( strlen( $decoded ) <= $nonce_size ) {
			return false;
		}
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
		$secret = defined( 'AUTH_KEY' ) && AUTH_KEY ? AUTH_KEY : wp_salt( 'auth' );
		return hash( 'sha256', $secret . 'citeoryx', true );
	}
}
