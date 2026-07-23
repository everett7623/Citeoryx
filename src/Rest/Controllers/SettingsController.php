<?php
/**
 * Settings REST controller.
 *
 * @package Citeoryx\Rest\Controllers
 */

namespace Citeoryx\Rest\Controllers;

use Citeoryx\Application\Settings\SiteProfileSchema;
use Citeoryx\Application\Notifications\WeeklyDigest;
use Citeoryx\Application\Notifications\CriticalIssueNotifier;
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
		return $this->success( $this->settings_payload() );
	}

	/**
	 * Update settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function update_settings( WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) || ! is_array( $params['settings'] ?? null ) || ! is_array( $params['profile'] ?? null ) ) {
			return $this->error( __( '设置请求格式无效。', 'citeoryx' ), 400 );
		}

		$settings_error = $this->validate_settings( $params['settings'] );
		if ( $settings_error ) {
			return $this->error( $settings_error, 400 );
		}

		$profile_schema = new SiteProfileSchema();
		$profile        = $profile_schema->sanitize( $params['profile'] );
		$profile_error  = $profile_schema->validation_error( $profile );
		if ( $profile_error ) {
			return $this->error( $profile_error, 400 );
		}

		$settings = $this->sanitize_settings( $params['settings'] );

		update_option( 'citeoryx_settings', $settings );
		update_option( 'citeoryx_site_profile', $profile );
		update_option( 'citeoryx_remove_data_on_uninstall', $settings['remove_data_on_uninstall'] );

		return $this->success( $this->settings_payload() );
	}

	/**
	 * Build the settings response using one stable contract.
	 *
	 * @return array<string, mixed>
	 */
	private function settings_payload(): array {
		$profile_schema  = new SiteProfileSchema();
		$profile         = $profile_schema->sanitize( get_option( 'citeoryx_site_profile', array() ) );
		$profile_payload = empty( $profile ) ? new \stdClass() : $profile;

		return array(
			'settings'              => $this->sanitize_settings( get_option( 'citeoryx_settings', array() ) ),
			'profile'               => $profile_payload,
			'profile_complete'      => $profile_schema->is_complete( $profile ),
			'profile_options'       => $profile_schema->options(),
			'notification_status'   => $this->notification_status(),
			'critical_alert_status' => $this->critical_alert_status(),
		);
	}

	/**
	 * Read optional notification state without blocking onboarding.
	 *
	 * @return array{status:string,message:string,attempted_at:string|null,recipient:string}
	 */
	private function notification_status(): array {
		try {
			return $this->container->get( WeeklyDigest::class )->get_status();
		} catch ( \Throwable $error ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Citeoryx] Unable to read notification status: ' . $error->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}

			return array(
				'status'       => 'never',
				'message'      => '',
				'attempted_at' => null,
				'recipient'    => '',
			);
		}
	}

	/**
	 * Read the optional serious-issue alert state.
	 *
	 * @return array{status:string,message:string,attempted_at:string|null,recipient:string,issue_count:int}
	 */
	private function critical_alert_status(): array {
		try {
			return $this->container->get( CriticalIssueNotifier::class )->get_status();
		} catch ( \Throwable $error ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Citeoryx] Unable to read critical alert status: ' . $error->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}

			return array(
				'status'       => 'never',
				'message'      => '',
				'attempted_at' => null,
				'recipient'    => '',
				'issue_count'  => 0,
			);
		}
	}

	/**
	 * Validate setting value types before casting them.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return string
	 */
	private function validate_settings( array $settings ): string {
		foreach ( array( 'auto_scan', 'remove_data_on_uninstall', 'weekly_digest_enabled', 'critical_alerts_enabled' ) as $key ) {
			if ( array_key_exists( $key, $settings ) && ! is_bool( $settings[ $key ] ) ) {
				return __( '设置包含无效的开关值。', 'citeoryx' );
			}
		}

		if ( array_key_exists( 'notification_email', $settings ) ) {
			if ( ! is_string( $settings['notification_email'] ) || ! is_email( sanitize_email( $settings['notification_email'] ) ) ) {
				return __( '通知邮箱地址无效。', 'citeoryx' );
			}
		}

		if ( array_key_exists( 'data_retention_days', $settings ) && ! is_int( $settings['data_retention_days'] ) ) {
			return __( '数据保留天数必须为整数。', 'citeoryx' );
		}

		return '';
	}

	/**
	 * Sanitize settings.
	 *
	 * @param mixed $settings Settings.
	 * @return array<string, mixed>
	 */
	private function sanitize_settings( $settings ): array {
		if ( ! is_array( $settings ) ) {
			return $this->default_settings();
		}

		$sanitized = $this->default_settings();
		if ( isset( $settings['data_retention_days'] ) && is_scalar( $settings['data_retention_days'] ) ) {
			$sanitized['data_retention_days'] = (int) $settings['data_retention_days'];
		}
		if ( isset( $settings['auto_scan'] ) && is_scalar( $settings['auto_scan'] ) ) {
			$sanitized['auto_scan'] = (bool) $settings['auto_scan'];
		}
		if ( isset( $settings['remove_data_on_uninstall'] ) && is_scalar( $settings['remove_data_on_uninstall'] ) ) {
			$sanitized['remove_data_on_uninstall'] = (bool) $settings['remove_data_on_uninstall'];
		}
		if ( isset( $settings['weekly_digest_enabled'] ) && is_scalar( $settings['weekly_digest_enabled'] ) ) {
			$sanitized['weekly_digest_enabled'] = (bool) $settings['weekly_digest_enabled'];
		}
		if ( isset( $settings['critical_alerts_enabled'] ) && is_scalar( $settings['critical_alerts_enabled'] ) ) {
			$sanitized['critical_alerts_enabled'] = (bool) $settings['critical_alerts_enabled'];
		}
		if ( isset( $settings['notification_email'] ) && is_scalar( $settings['notification_email'] ) ) {
			$sanitized['notification_email'] = sanitize_email( (string) $settings['notification_email'] );
		}

		return $sanitized;
	}

	/**
	 * Get default settings.
	 *
	 * @return array<string, mixed>
	 */
	private function default_settings(): array {
		return array(
			'auto_scan'                => true,
			'remove_data_on_uninstall' => false,
			'weekly_digest_enabled'    => false,
			'critical_alerts_enabled'  => false,
			'notification_email'       => sanitize_email( (string) get_option( 'admin_email' ) ),
		);
	}
}
