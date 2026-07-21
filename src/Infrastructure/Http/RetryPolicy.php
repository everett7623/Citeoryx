<?php
/**
 * HTTP retry policy.
 *
 * @package Citeoryx\Infrastructure\Http
 */

namespace Citeoryx\Infrastructure\Http;

/**
 * Retries transient WordPress HTTP failures with bounded backoff.
 */
class RetryPolicy {

	const DEFAULT_MAX_ATTEMPTS  = 3;
	const DEFAULT_BASE_DELAY_MS = 250;
	const DEFAULT_MAX_DELAY_MS  = 2000;

	private int $max_attempts;
	private int $base_delay_ms;
	private int $max_delay_ms;
	private int $last_attempt_count = 0;

	/**
	 * Delay callback.
	 *
	 * @var \Closure(int): void
	 */
	private \Closure $sleeper;

	/**
	 * Constructor.
	 *
	 * @param int           $max_attempts Maximum request attempts.
	 * @param callable|null $sleeper Optional delay callback for tests.
	 * @param int           $base_delay_ms Base exponential delay in milliseconds.
	 * @param int           $max_delay_ms Maximum synchronous delay per retry.
	 */
	public function __construct(
		int $max_attempts = self::DEFAULT_MAX_ATTEMPTS,
		?callable $sleeper = null,
		int $base_delay_ms = self::DEFAULT_BASE_DELAY_MS,
		int $max_delay_ms = self::DEFAULT_MAX_DELAY_MS
	) {
		$this->max_attempts  = max( 1, $max_attempts );
		$this->base_delay_ms = max( 0, $base_delay_ms );
		$this->max_delay_ms  = max( 0, $max_delay_ms );
		$this->sleeper       = $sleeper
			? \Closure::fromCallable( $sleeper )
			: static function ( int $delay_ms ): void {
				if ( $delay_ms > 0 ) {
					usleep( $delay_ms * 1000 );
				}
			};
	}

	/**
	 * Execute a request callback until it succeeds or should not be retried.
	 *
	 * @param callable $request Request callback returning a WP HTTP response.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute( callable $request ) {
		$this->last_attempt_count = 0;
		for ( $attempt = 1; $attempt <= $this->max_attempts; ++$attempt ) {
			$this->last_attempt_count = $attempt;
			$response                 = $request();

			if ( $attempt >= $this->max_attempts || ! $this->is_retryable( $response ) ) {
				return $response;
			}

			( $this->sleeper )( $this->delay_milliseconds( $response, $attempt ) );
		}

		return new \WP_Error( 'retry_exhausted', 'HTTP retry attempts exhausted.' );
	}

	/**
	 * Return the number of attempts made by the last execution.
	 *
	 * @return int
	 */
	public function get_last_attempt_count(): int {
		return $this->last_attempt_count;
	}

	/**
	 * Check whether a response represents a transient failure.
	 *
	 * @param mixed $response WordPress HTTP response or WP_Error.
	 * @return bool
	 */
	private function is_retryable( $response ): bool {
		if ( is_wp_error( $response ) ) {
			return true;
		}

		$code = wp_remote_retrieve_response_code( $response );
		return 429 === $code || ( $code >= 500 && $code <= 599 );
	}

	/**
	 * Resolve a bounded retry delay from Retry-After or exponential backoff.
	 *
	 * @param mixed $response WordPress HTTP response or WP_Error.
	 * @param int   $attempt Completed attempt number.
	 * @return int Delay in milliseconds.
	 */
	private function delay_milliseconds( $response, int $attempt ): int {
		$retry_after_ms = $this->retry_after_milliseconds( $response );
		if ( null !== $retry_after_ms ) {
			return min( $this->max_delay_ms, max( 0, $retry_after_ms ) );
		}

		$delay = $this->base_delay_ms * ( 2 ** max( 0, $attempt - 1 ) );
		return min( $this->max_delay_ms, $delay );
	}

	/**
	 * Parse Retry-After as seconds or an HTTP date.
	 *
	 * @param mixed $response WordPress HTTP response or WP_Error.
	 * @return int|null Delay in milliseconds, or null when absent/invalid.
	 */
	private function retry_after_milliseconds( $response ): ?int {
		if ( is_wp_error( $response ) ) {
			return null;
		}

		$value = wp_remote_retrieve_header( $response, 'retry-after' );
		if ( ! is_scalar( $value ) || '' === trim( (string) $value ) ) {
			return null;
		}

		$value = trim( (string) $value );
		if ( ctype_digit( $value ) ) {
			return (int) $value * 1000;
		}

		$timestamp = strtotime( $value );
		return false === $timestamp ? null : max( 0, $timestamp - time() ) * 1000;
	}
}
