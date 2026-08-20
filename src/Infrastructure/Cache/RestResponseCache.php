<?php
/**
 * REST response cache.
 *
 * @package Citeoryx\Infrastructure\Cache
 */

namespace Citeoryx\Infrastructure\Cache;

/**
 * Versioned cache for user-neutral aggregate REST payloads.
 */
class RestResponseCache {

	private const VERSION_OPTION = 'citeoryx_rest_response_cache_version';
	private const TTL            = 300;

	private static bool $dirty               = false;
	private static bool $shutdown_registered = false;

	private Transients $transients;

	public function __construct( Transients $transients ) {
		$this->transients = $transients;
	}

	/**
	 * Return a cached payload or resolve and retain it.
	 *
	 * @param string   $key Cache key.
	 * @param callable $resolver Payload resolver.
	 * @return array<string, mixed>
	 */
	public function remember( string $key, callable $resolver ): array {
		self::flush();

		$cache_key = $this->key( $key, self::generation() );
		$cached    = $this->transients->get( $cache_key );
		if ( is_array( $cached ) && isset( $cached['payload'] ) && is_array( $cached['payload'] ) ) {
			return $cached['payload'];
		}

		$payload = $resolver();
		$this->transients->set( $cache_key, array( 'payload' => $payload ), self::TTL );

		return $payload;
	}

	/**
	 * Queue one cache generation change for the current request.
	 *
	 * @return void
	 */
	public static function invalidate(): void {
		self::$dirty = true;
		if ( self::$shutdown_registered ) {
			return;
		}

		add_action( 'shutdown', array( self::class, 'flush' ), PHP_INT_MAX );
		self::$shutdown_registered = true;
	}

	/**
	 * Commit a pending generation change.
	 *
	 * @return void
	 */
	public static function flush(): void {
		if ( ! self::$dirty ) {
			return;
		}

		update_option( self::VERSION_OPTION, wp_generate_uuid4(), false );
		self::$dirty = false;
	}

	/**
	 * Read or initialize the current generation.
	 *
	 * @return string
	 */
	private static function generation(): string {
		$generation = get_option( self::VERSION_OPTION, false );
		if ( false !== $generation ) {
			return (string) $generation;
		}

		$generation = wp_generate_uuid4();
		if ( add_option( self::VERSION_OPTION, $generation, '', false ) ) {
			return $generation;
		}

		return (string) get_option( self::VERSION_OPTION, wp_generate_uuid4() );
	}

	/**
	 * Build a bounded transient key.
	 *
	 * @param string $key Logical key.
	 * @param string $generation Cache generation.
	 * @return string
	 */
	private function key( string $key, string $generation ): string {
		return 'rest_response_' . $generation . '_' . md5( $key . '|' . CITEORYX_VERSION );
	}
}
