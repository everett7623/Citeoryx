<?php
/**
 * Transient cache wrapper.
 *
 * @package Citeoryx\Infrastructure\Cache
 */

namespace Citeoryx\Infrastructure\Cache;

/**
 * Simple transient-based cache with namespacing.
 */
class Transients {

	/**
	 * Get cached value.
	 *
	 * @param string $key Cache key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get( string $key, $default = null ) {
		$value = get_transient( $this->key( $key ) );
		return false === $value ? $default : $value;
	}

	/**
	 * Set cached value.
	 *
	 * @param string $key Cache key.
	 * @param mixed  $value Value.
	 * @param int    $ttl TTL in seconds.
	 * @return bool
	 */
	public function set( string $key, $value, int $ttl = HOUR_IN_SECONDS ): bool {
		return set_transient( $this->key( $key ), $value, $ttl );
	}

	/**
	 * Delete cached value.
	 *
	 * @param string $key Cache key.
	 * @return bool
	 */
	public function delete( string $key ): bool {
		return delete_transient( $this->key( $key ) );
	}

	/**
	 * Build namespaced key.
	 *
	 * @param string $key Original key.
	 * @return string
	 */
	private function key( string $key ): string {
		return 'citeoryx_' . $key;
	}
}
