<?php
/**
 * Logger utility.
 *
 * @package Citeoryx\Infrastructure\Logging
 */

namespace Citeoryx\Infrastructure\Logging;

/**
 * Centralized logging for Citeoryx.
 */
class Logger {

	/**
	 * Log error message.
	 *
	 * @param string               $message Error message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	public static function error( string $message, array $context = array() ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$formatted = sprintf(
				'[Citeoryx] %s %s',
				$message,
				$context ? wp_json_encode( $context ) : ''
			);
			error_log( $formatted ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Log info message.
	 *
	 * @param string               $message Info message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	public static function info( string $message, array $context = array() ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$formatted = sprintf(
				'[Citeoryx] %s %s',
				$message,
				$context ? wp_json_encode( $context ) : ''
			);
			error_log( $formatted ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Log warning message.
	 *
	 * @param string               $message Warning message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	public static function warning( string $message, array $context = array() ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$formatted = sprintf(
				'[Citeoryx] WARNING: %s %s',
				$message,
				$context ? wp_json_encode( $context ) : ''
			);
			error_log( $formatted ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
