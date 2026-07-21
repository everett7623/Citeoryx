<?php
/**
 * Reports REST controller.
 *
 * @package Citeoryx\Rest\Controllers
 */

namespace Citeoryx\Rest\Controllers;

use Citeoryx\Core\Capabilities;
use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Domain\Issue\IssueRepository;
use Citeoryx\Domain\Metrics\MetricsRepository;
use Citeoryx\Domain\Scan\ScanRunRepository;
use Citeoryx\Integrations\SeoPlugins\SeoPluginAdapterFactory;
use WP_REST_Request;

/**
 * Provides a compact, exportable report summary.
 */
class ReportsController extends BaseController {

	/**
	 * Register routes.
	 *
	 * @param string $namespace Namespace.
	 * @return void
	 */
	public function register( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/reports/summary',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_summary' ),
					'permission_callback' => array( $this, 'get_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Check report access.
	 *
	 * @return bool
	 */
	public function get_permissions_check(): bool {
		return $this->check_cap( Capabilities::VIEW_DASHBOARD );
	}

	/**
	 * Build the report summary.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_summary( WP_REST_Request $request ): \WP_REST_Response {
		$content_repo = $this->container->get( ContentRepository::class );
		$issue_repo   = $this->container->get( IssueRepository::class );
		$scan_repo    = $this->container->get( ScanRunRepository::class );
		$metrics_repo = $this->container->get( MetricsRepository::class );
		$seo_factory  = $this->container->get( SeoPluginAdapterFactory::class );

		$content       = $content_repo->report_summary();
		$status_counts = $content_repo->count_by_status();
		$open_issues   = $issue_repo->list( array( 'status' => 'open' ), 1, 5 );
		$recent_scans  = $scan_repo->list( 1, 5 );
		$performance   = $metrics_repo->aggregate_site( 28 );

		return $this->success(
			array(
				'generated_at' => current_time( 'mysql' ),
				'content'      => array(
					'total'                      => $content['total'],
					'status_counts'              => $this->normalize_counts( $status_counts ),
					'average_health_score'       => $content['average_health_score'],
					'average_ai_readiness_score' => $content['average_ai_readiness_score'],
					'last_scanned_at'            => $content['last_scanned_at'],
				),
				'issues'       => array(
					'open_total'      => $open_issues['total'],
					'severity_counts' => $issue_repo->count_open_by( 'severity' ),
					'category_counts' => $issue_repo->count_open_by( 'category' ),
					'top_items'       => array_map( static fn ( $issue ) => $issue->to_array(), $open_issues['items'] ),
				),
				'scans'        => array(
					'recent' => array_map( static fn ( $scan ) => $scan->to_array(), $recent_scans['items'] ),
				),
				'performance'  => array_merge( array( 'period_days' => 28 ), $performance ),
				'plugin'       => array(
					'version'    => CITEORYX_VERSION,
					'seo_plugin' => $seo_factory->detect(),
				),
			)
		);
	}

	/**
	 * Convert the existing status map to stable list items.
	 *
	 * @param array<string, int> $counts Status counts.
	 * @return array<int, array{label:string,count:int}>
	 */
	private function normalize_counts( array $counts ): array {
		$result = array();
		ksort( $counts );
		foreach ( $counts as $label => $count ) {
			$result[] = array(
				'label' => (string) $label,
				'count' => (int) $count,
			);
		}
		return $result;
	}
}
