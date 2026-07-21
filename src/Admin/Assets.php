<?php
/**
 * Admin asset loader.
 *
 * @package Citeoryx\Admin
 */

namespace Citeoryx\Admin;

use Citeoryx\Core\Container;
use Citeoryx\Core\Capabilities;

/**
 * Enqueues admin scripts and styles.
 */
class Assets {

	private Container $container;

	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue( string $hook ): void {
		if ( strpos( $hook, 'citeoryx' ) === false ) {
			return;
		}

		$asset_file = CITEORYX_PLUGIN_DIR . 'assets/build/index.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : array(
			'dependencies' => array(),
			'version'      => CITEORYX_VERSION,
		);

		wp_enqueue_script(
			'citeoryx-admin',
			CITEORYX_PLUGIN_URL . 'assets/build/index.js',
			array_merge( $asset['dependencies'], array( 'wp-api-fetch', 'wp-components', 'wp-element', 'wp-i18n' ) ),
			$asset['version'] ?? CITEORYX_VERSION,
			true
		);

		wp_enqueue_style(
			'citeoryx-admin',
			CITEORYX_PLUGIN_URL . 'assets/build/style-index.css',
			array( 'wp-components' ),
			CITEORYX_VERSION
		);

		$current_user = wp_get_current_user();

		wp_localize_script(
			'citeoryx-admin',
			'citeoryxAdmin',
			array(
				'restUrl'   => esc_url_raw( rest_url( CITEORYX_REST_NAMESPACE ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'assetsUrl' => CITEORYX_PLUGIN_URL . 'assets/',
				'pluginUrl' => CITEORYX_PLUGIN_URL,
				'user'      => array(
					'id'                    => $current_user->ID,
					'name'                  => $current_user->display_name,
					'canViewDashboard'      => current_user_can( Capabilities::VIEW_DASHBOARD ),
					'canViewContent'        => current_user_can( Capabilities::VIEW_CONTENT ),
					'canScan'               => current_user_can( Capabilities::RUN_SCANS ),
					'canManageIssues'       => current_user_can( Capabilities::MANAGE_ISSUES ),
					'canManageIntegrations' => current_user_can( Capabilities::MANAGE_INTEGRATIONS ),
					'canSettings'           => current_user_can( Capabilities::MANAGE_SETTINGS ),
					'canExport'             => current_user_can( Capabilities::EXPORT_DATA ),
				),
			)
		);
	}
}
