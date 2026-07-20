<?php
/**
 * Plugin activation handler.
 *
 * @package Citeoryx\Core
 */

namespace Citeoryx\Core;

use Citeoryx\Infrastructure\Database\SchemaManager;

/**
 * Handles plugin activation.
 */
class Activator {

	/**
	 * Activate the plugin.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::check_requirements();

		$schema_manager = new SchemaManager();
		$schema_manager->install();

		Capabilities::assign();

		update_option( 'citeoryx_version', CITEORYX_VERSION );
		update_option( 'citeoryx_db_version', CITEORYX_DB_VERSION );
		update_option( 'citeoryx_activated_at', current_time( 'mysql' ) );
		update_option( 'citeoryx_remove_data_on_uninstall', false );

		set_transient( 'citeoryx_activation_redirect', true, 30 );

		if ( ! wp_next_scheduled( 'citeoryx_hourly_change_detection' ) ) {
			wp_schedule_event( time(), 'hourly', 'citeoryx_hourly_change_detection' );
		}
		if ( ! wp_next_scheduled( 'citeoryx_daily_incremental_scan' ) ) {
			wp_schedule_event( time(), 'daily', 'citeoryx_daily_incremental_scan' );
		}
		if ( ! wp_next_scheduled( 'citeoryx_weekly_health_recalc' ) ) {
			wp_schedule_event( time(), 'weekly', 'citeoryx_weekly_health_recalc' );
		}
		if ( ! wp_next_scheduled( 'citeoryx_weekly_link_check' ) ) {
			wp_schedule_event( time(), 'weekly', 'citeoryx_weekly_link_check' );
		}
	}

	/**
	 * Check minimum requirements.
	 *
	 * @return void
	 */
	private static function check_requirements(): void {
		if ( version_compare( phpversion(), '8.0', '<' ) ) {
			wp_die( esc_html__( 'Citeoryx requires PHP 8.0 or higher.', 'citeoryx' ) );
		}
		if ( version_compare( $GLOBALS['wp_version'], '6.6', '<' ) ) {
			wp_die( esc_html__( 'Citeoryx requires WordPress 6.6 or higher.', 'citeoryx' ) );
		}
	}
}
