<?php
/**
 * Scans REST controller.
 *
 * @package Citeoryx\Rest\Controllers
 */

namespace Citeoryx\Rest\Controllers;

use Citeoryx\Core\Capabilities;
use Citeoryx\Domain\Scan\ScanRun;
use Citeoryx\Domain\Scan\ScanRunRepository;
use Citeoryx\Application\Scan\ContentScanner;
use WP_REST_Request;

/**
 * Scan task endpoints.
 */
class ScansController extends BaseController {

	/**
	 * Register routes.
	 *
	 * @param string $namespace Namespace.
	 * @return void
	 */
	public function register( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/scans',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_scan' ),
					'permission_callback' => array( $this, 'scan_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/scans/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_scan' ),
					'permission_callback' => array( $this, 'scan_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Permission check.
	 *
	 * @return bool
	 */
	public function scan_permissions_check(): bool {
		return $this->check_cap( Capabilities::RUN_SCANS );
	}

	/**
	 * Create a scan run.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function create_scan( WP_REST_Request $request ): \WP_REST_Response {
		$params    = $request->get_json_params();
		$scan_type = ! empty( $params['scan_type'] ) ? sanitize_text_field( $params['scan_type'] ) : 'full';

		$run               = new ScanRun();
		$run->scan_type    = $scan_type;
		$run->status       = 'running';
		$run->trigger_type = 'manual';
		$run->config       = array( 'requested_by' => get_current_user_id() );

		$repo = $this->container->get( ScanRunRepository::class );
		$id   = $repo->create( $run );

		// Run synchronously for small sites; large sites should use Action Scheduler.
		$scanner = $this->container->get( ContentScanner::class );
		$issue_engine = $this->container->get( \Citeoryx\Application\Analyze\IssueEngine::class );

		$run->id = $id;
		$count   = 0;
		$failed  = 0;

		try {
			$processed = $scanner->scan_all();
			// Re-analyze top 500 recently modified items.
			$content_repo = $this->container->get( \Citeoryx\Domain\Content\ContentRepository::class );
			$recent       = $content_repo->list( array(), 1, 500 );
			foreach ( $recent['items'] as $item ) {
				$issue_engine->analyze( $item );
				++$count;
			}

			$repo->update_progress( $id, $count, $failed, 'completed' );
		} catch ( \Throwable $e ) {
			++$failed;
			$repo->update_progress( $id, $count, $failed, 'failed' );
			return $this->error( $e->getMessage(), 500 );
		}

		$run = $repo->find( $id );

		return $this->success( $run ? $run->to_array() : array( 'id' => $id ) );
	}

	/**
	 * Get scan progress.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_scan( WP_REST_Request $request ): \WP_REST_Response {
		$repo = $this->container->get( ScanRunRepository::class );
		$run  = $repo->find( (int) $request->get_param( 'id' ) );

		if ( ! $run ) {
			return $this->error( __( 'Scan not found.', 'citeoryx' ), 404 );
		}

		return $this->success( $run->to_array() );
	}
}
