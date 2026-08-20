<?php
/**
 * Dashboard REST controller.
 *
 * @package Citeoryx\Rest\Controllers
 */

namespace Citeoryx\Rest\Controllers;

use Citeoryx\Core\Capabilities;
use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Domain\Issue\IssueRepository;
use Citeoryx\Domain\Scan\ScanRunRepository;
use Citeoryx\Infrastructure\Cache\RestResponseCache;
use Citeoryx\Integrations\SeoPlugins\SeoPluginAdapterFactory;
use WP_REST_Request;

/**
 * Dashboard data endpoint.
 */
class DashboardController extends BaseController {

	/**
	 * Register routes.
	 *
	 * @param string $namespace Namespace.
	 * @return void
	 */
	public function register( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/dashboard',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_dashboard' ),
					'permission_callback' => array( $this, 'get_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Permission check.
	 *
	 * @return bool
	 */
	public function get_permissions_check(): bool {
		return $this->check_cap( Capabilities::VIEW_DASHBOARD );
	}

	/**
	 * Get dashboard data.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_dashboard( WP_REST_Request $request ): \WP_REST_Response {
		$cache = $this->container->get( RestResponseCache::class );
		$data  = $cache->remember(
			'dashboard',
			function (): array {
				$content_repo = $this->container->get( ContentRepository::class );
				$issue_repo   = $this->container->get( IssueRepository::class );
				$scan_repo    = $this->container->get( ScanRunRepository::class );
				$seo_factory  = $this->container->get( SeoPluginAdapterFactory::class );

				$status_counts = $content_repo->count_by_status();
				$open_issues   = $issue_repo->list( array( 'status' => 'open' ), 1, 5 );
				$recent_scans  = $scan_repo->list( 1, 5 );
				$high_priority = $issue_repo->list(
					array(
						'status'   => 'open',
						'severity' => 'high',
					),
					1,
					5
				);

				return array(
					'status_counts'  => $status_counts,
					'total_content'  => array_sum( $status_counts ),
					'open_issues'    => array_map( static fn ( $i ) => $i->to_array(), $open_issues['items'] ),
					'high_priority'  => array_map( static fn ( $i ) => $i->to_array(), $high_priority['items'] ),
					'recent_scans'   => array_map( static fn ( $s ) => $s->to_array(), $recent_scans['items'] ),
					'seo_plugin'     => $seo_factory->detect(),
					'plugin_version' => CITEORYX_VERSION,
				);
			}
		);

		return $this->success( $data );
	}
}
