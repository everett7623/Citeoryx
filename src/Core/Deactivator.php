<?php
/**
 * Plugin deactivation handler.
 *
 * @package Citeoryx\Core
 */

namespace Citeoryx\Core;

use Citeoryx\Application\Notifications\WeeklyDigest;

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
			'citeoryx_daily_search_performance_import',
			'citeoryx_scan_single_post',
			'citeoryx_run_scan',
			'citeoryx_recalc_health_batch',
			'citeoryx_check_links_batch',
			'citeoryx_import_search_performance_batch',
			WeeklyDigest::HOOK,
		);

		foreach ( $timestamps as $hook ) {
			wp_clear_scheduled_hook( $hook );
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( $hook, array(), 'citeoryx' );
			}
		}
	}
}
