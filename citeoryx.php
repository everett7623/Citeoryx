<?php
/**
 * Plugin Name: Citeoryx
 * Plugin URI:  https://citeoryx.com
 * Description: WordPress 内容健康度持续监控、优化与 AI 可发现性引擎
 * Version:     2.3.1
 * Author:      everettlabs
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: citeoryx
 * Domain Path: /languages
 * Requires at least: 6.6
 * Requires PHP: 8.0
 *
 * @package Citeoryx
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CITEORYX_VERSION', '2.3.1' );
define( 'CITEORYX_PLUGIN_FILE', __FILE__ );
define( 'CITEORYX_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CITEORYX_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CITEORYX_TEXT_DOMAIN', 'citeoryx' );

if ( file_exists( CITEORYX_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once CITEORYX_PLUGIN_DIR . 'vendor/autoload.php';
} else {
	require_once CITEORYX_PLUGIN_DIR . 'src/Core/Autoloader.php';
	\Citeoryx\Core\Autoloader::register();
}

require_once CITEORYX_PLUGIN_DIR . 'src/Core/constants.php';

register_activation_hook( __FILE__, array( 'Citeoryx\Core\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Citeoryx\Core\Deactivator', 'deactivate' ) );

add_action( 'plugins_loaded', 'citeoryx_boot_plugin' );

if ( ! function_exists( 'citeoryx_boot_plugin' ) ) {
	/**
	 * Boot the Citeoryx plugin.
	 *
	 * @return void
	 */
	function citeoryx_boot_plugin(): void {
		if ( version_compare( phpversion(), '8.0', '<' ) ) {
			add_action( 'admin_notices', 'citeoryx_php_version_notice' );
			return;
		}

		$container = new \Citeoryx\Core\Container();
		$plugin    = new \Citeoryx\Core\Plugin( $container );
		$plugin->run();
	}
}

if ( ! function_exists( 'citeoryx_php_version_notice' ) ) {
	/**
	 * Display PHP version notice.
	 *
	 * @return void
	 */
	function citeoryx_php_version_notice(): void {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Citeoryx requires PHP 8.0 or higher.', 'citeoryx' )
		);
	}
}
