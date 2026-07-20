<?php
/**
 * Plugin deactivation handler.
 *
 * @package Citeoryx\Core
 */

namespace Citeoryx\Core;

/**
 * Handles plugin deactivation.
 */
class Deactivator {

	/**
	 * Deactivate the plugin.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		$timestamps = array(
			'citeoryx_hourly_change_detection',
			'citeoryx_daily_incremental_scan',
			'citeoryx_weekly_health_recalc',
			'citeoryx_weekly_link_check',
		);

		foreach ( $timestamps as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
		}
	}
}
