<?php
/**
 * Settings REST controller.
 *
 * @package Citeoryx\Rest\Controllers
 */

namespace Citeoryx\Rest\Controllers;

use Citeoryx\Core\Capabilities;
use WP_REST_Request;

/**
 * Plugin settings endpoints.
 */
class SettingsController extends BaseController {

	/**
	 * Register routes.
	 *
	 * @param string $namespace Namespace.
	 * @return void
	 */
	public function register( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'settings_permissions_check' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'settings_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Permission check.
	 *
	 * @return bool
	 */
	public function settings_permissions_check(): bool {
		return $this->check_cap( Capabilities::MANAGE_SETTINGS );
	}

	/**
	 * Get settings.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings(): \WP_REST_Response {
		$settings = get_option( 'citeoryx_settings', array() );
		$profile  = get_option( 'citeoryx_site_profile', array() );

		return $this->success(
			array(
				'settings' => $settings,
				'profile'  => $profile,
			)
		);
	}

	/**
	 * Update settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function update_settings( WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$settings = is_array( $params['settings'] ?? null ) ? $this->sanitize_settings( $params['settings'] ) : array();
		$profile  = is_array( $params['profile'] ?? null ) ? $this->sanitize_profile( $params['profile'] ) : array();

		update_option( 'citeoryx_settings', $settings );
		update_option( 'citeoryx_site_profile', $profile );

		return $this->success(
			array(
				'settings' => $settings,
				'profile'  => $profile,
			)
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param mixed $settings Settings.
	 * @return array<string, mixed>
	 */
	private function sanitize_settings( $settings ): array {
		if ( ! is_array( $settings ) ) {
			return array();
		}

		$sanitized = array();
		if ( isset( $settings['data_retention_days'] ) && is_scalar( $settings['data_retention_days'] ) ) {
			$sanitized['data_retention_days'] = (int) $settings['data_retention_days'];
		}
		if ( isset( $settings['auto_scan'] ) && is_scalar( $settings['auto_scan'] ) ) {
			$sanitized['auto_scan'] = (bool) $settings['auto_scan'];
		}
		if ( isset( $settings['remove_data_on_uninstall'] ) && is_scalar( $settings['remove_data_on_uninstall'] ) ) {
			$sanitized['remove_data_on_uninstall'] = (bool) $settings['remove_data_on_uninstall'];
			update_option( 'citeoryx_remove_data_on_uninstall', $sanitized['remove_data_on_uninstall'] );
		}

		return $sanitized;
	}

	/**
	 * Sanitize profile.
	 *
	 * @param mixed $profile Profile.
	 * @return array<string, mixed>
	 */
	private function sanitize_profile( $profile ): array {
		if ( ! is_array( $profile ) ) {
			return array();
		}

		$allowed   = array( 'site_type', 'primary_goal', 'main_language', 'main_region', 'update_rhythm', 'risk_level', 'review_cycle_days' );
		$sanitized = array();
		foreach ( $allowed as $key ) {
			if ( isset( $profile[ $key ] ) && is_scalar( $profile[ $key ] ) ) {
				$sanitized[ $key ] = sanitize_text_field( (string) $profile[ $key ] );
			}
		}

		return $sanitized;
	}
}
