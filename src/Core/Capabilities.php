<?php
/**
 * Custom plugin capabilities.
 *
 * @package Citeoryx\Core
 */

namespace Citeoryx\Core;

/**
 * Capability definitions and role mapping.
 */
class Capabilities {

	public const VIEW_DASHBOARD    = 'citeoryx_view_dashboard';
	public const VIEW_CONTENT      = 'citeoryx_view_content';
	public const RUN_SCANS         = 'citeoryx_run_scans';
	public const MANAGE_ISSUES     = 'citeoryx_manage_issues';
	public const USE_AI            = 'citeoryx_use_ai';
	public const APPLY_CHANGES     = 'citeoryx_apply_changes';
	public const MANAGE_INTEGRATIONS = 'citeoryx_manage_integrations';
	public const MANAGE_SETTINGS   = 'citeoryx_manage_settings';
	public const EXPORT_DATA       = 'citeoryx_export_data';

	/**
	 * Get all capability slugs.
	 *
	 * @return array<string>
	 */
	public static function all(): array {
		return array(
			self::VIEW_DASHBOARD,
			self::VIEW_CONTENT,
			self::RUN_SCANS,
			self::MANAGE_ISSUES,
			self::USE_AI,
			self::APPLY_CHANGES,
			self::MANAGE_INTEGRATIONS,
			self::MANAGE_SETTINGS,
			self::EXPORT_DATA,
		);
	}

	/**
	 * Assign default capabilities to WordPress roles.
	 *
	 * @return void
	 */
	public static function assign(): void {
		$map = array(
			'administrator' => self::all(),
			'editor'        => array(
				self::VIEW_DASHBOARD,
				self::VIEW_CONTENT,
				self::RUN_SCANS,
				self::MANAGE_ISSUES,
				self::USE_AI,
				self::APPLY_CHANGES,
				self::EXPORT_DATA,
			),
			'author'        => array(
				self::VIEW_DASHBOARD,
				self::VIEW_CONTENT,
				self::MANAGE_ISSUES,
			),
			'contributor'   => array(
				self::VIEW_DASHBOARD,
				self::VIEW_CONTENT,
			),
		);

		foreach ( $map as $role_name => $caps ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( $caps as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}
}
