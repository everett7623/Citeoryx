<?php
/**
 * Built-in PSR-4 autoloader fallback.
 *
 * @package Citeoryx\Core
 */

namespace Citeoryx\Core;

/**
 * Minimal PSR-4 autoloader for Citeoryx namespace.
 */
class Autoloader {

	/**
	 * Register autoloader.
	 *
	 * @return bool
	 */
	public static function register(): bool {
		return spl_autoload_register( array( self::class, 'load' ) );
	}

	/**
	 * Load a class.
	 *
	 * @param string $class Full class name.
	 * @return void
	 */
	public static function load( string $class ): void {
		$prefix = 'Citeoryx\\';
		$base_dir = CITEORYX_PLUGIN_DIR . 'src/';

		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $len );
		$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
}
