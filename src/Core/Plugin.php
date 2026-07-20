<?php
/**
 * Main plugin class.
 *
 * @package Citeoryx\Core
 */

namespace Citeoryx\Core;

use Citeoryx\Admin\Menu;
use Citeoryx\Admin\Assets;
use Citeoryx\Admin\Notices;
use Citeoryx\Rest\Router;
use Citeoryx\Infrastructure\Queue\Scheduler;
use Citeoryx\Infrastructure\Database\SchemaManager;
use Citeoryx\Support\Privacy;
use Citeoryx\Integrations\SearchConsole\GoogleOAuth;

/**
 * Main plugin orchestrator.
 */
class Plugin {

	/**
	 * Service container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Constructor.
	 *
	 * @param Container $container Service container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Run the plugin.
	 *
	 * @return void
	 */
	public function run(): void {
		$this->set_locale();
		$this->register_capabilities();
		$this->register_admin();
		$this->register_rest();
		$this->register_queue();
		$this->register_privacy();
		$this->run_migrations();
		$this->register_cli();
	}

	/**
	 * Register WP-CLI commands.
	 *
	 * @return void
	 */
	private function register_cli(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		\WP_CLI::add_command( 'citeoryx', 'Citeoryx\Cli\ScanCommand' );
	}

	/**
	 * Set locale / text domain.
	 *
	 * @return void
	 */
	private function set_locale(): void {
		add_action(
			'plugins_loaded',
			static function () {
				load_plugin_textdomain( 'citeoryx', false, dirname( plugin_basename( CITEORYX_PLUGIN_FILE ) ) . '/languages' );
			}
		);
	}

	/**
	 * Register custom capabilities.
	 *
	 * @return void
	 */
	private function register_capabilities(): void {
		$capabilities = new Capabilities();
		add_action( 'admin_init', array( $capabilities, 'assign' ) );
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	private function register_admin(): void {
		$menu    = new Menu();
		$assets  = new Assets( $this->container );
		$notices = new Notices();

		add_action( 'admin_menu', array( $menu, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $assets, 'enqueue' ) );
		add_action( 'admin_notices', array( $notices, 'render' ) );
		add_action( 'admin_init', array( $notices, 'activation_redirect' ) );
		add_action( 'admin_init', array( $this, 'handle_gsc_oauth_callback' ) );
	}

	public function handle_gsc_oauth_callback(): void {
		if ( ! isset( $_GET['action'] ) || GoogleOAuth::REDIRECT_ACTION !== $_GET['action'] ) {
			return;
		}

		if ( ! current_user_can( Capabilities::MANAGE_INTEGRATIONS ) ) {
			wp_die( esc_html__( 'You do not have permission to connect Google Search Console.', 'citeoryx' ) );
		}

		$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$oauth = new GoogleOAuth();
		$result = $code && $state && $oauth->handle_callback( $code, $state );

		set_transient( 'citeoryx_gsc_connection_result', $result ? 'connected' : 'failed', 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=citeoryx' ) );
		exit;
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	private function register_rest(): void {
		$router = new Router( $this->container );
		add_action( 'rest_api_init', array( $router, 'register_routes' ) );
	}

	/**
	 * Register queue / scheduled tasks.
	 *
	 * @return void
	 */
	private function register_queue(): void {
		$scheduler = $this->container->get( Scheduler::class );
		add_action( 'citeoryx_hourly_change_detection', array( $scheduler, 'detect_changes' ) );
		add_action( 'citeoryx_daily_incremental_scan', array( $scheduler, 'run_incremental_scan' ) );
		add_action( 'citeoryx_weekly_health_recalc', array( $scheduler, 'recalc_health' ) );
		add_action( 'citeoryx_weekly_link_check', array( $scheduler, 'check_links' ) );
	}

	/**
	 * Register privacy API.
	 *
	 * @return void
	 */
	private function register_privacy(): void {
		$privacy = new Privacy();
		add_action( 'admin_init', array( $privacy, 'register' ) );
	}

	/**
	 * Run database migrations if needed.
	 *
	 * @return void
	 */
	private function run_migrations(): void {
		$schema_manager = $this->container->get( SchemaManager::class );
		add_action( 'plugins_loaded', array( $schema_manager, 'maybe_upgrade' ), 20 );
	}
}
