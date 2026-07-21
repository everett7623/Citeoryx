<?php
/**
 * REST API router.
 *
 * @package Citeoryx\Rest
 */

namespace Citeoryx\Rest;

use Citeoryx\Core\Container;
use Citeoryx\Rest\Controllers\DashboardController;
use Citeoryx\Rest\Controllers\ContentController;
use Citeoryx\Rest\Controllers\IssuesController;
use Citeoryx\Rest\Controllers\ScansController;
use Citeoryx\Rest\Controllers\SettingsController;
use Citeoryx\Rest\Controllers\OptimizerController;
use Citeoryx\Rest\Controllers\SearchConsoleController;
use Citeoryx\Rest\Controllers\AiController;
use Citeoryx\Rest\Controllers\BingController;
use Citeoryx\Rest\Controllers\NotificationsController;
use Citeoryx\Rest\Controllers\ReportsController;

/**
 * Registers REST routes.
 */
class Router {

	private Container $container;

	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Register all routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$namespace = CITEORYX_REST_NAMESPACE;

		( new DashboardController( $this->container ) )->register( $namespace );
		( new ContentController( $this->container ) )->register( $namespace );
		( new IssuesController( $this->container ) )->register( $namespace );
		( new ScansController( $this->container ) )->register( $namespace );
		( new SettingsController( $this->container ) )->register( $namespace );
		( new OptimizerController( $this->container ) )->register( $namespace );
		( new SearchConsoleController( $this->container ) )->register( $namespace );
		( new BingController( $this->container ) )->register( $namespace );
		( new AiController( $this->container ) )->register( $namespace );
		( new NotificationsController( $this->container ) )->register( $namespace );
		( new ReportsController( $this->container ) )->register( $namespace );
	}
}
