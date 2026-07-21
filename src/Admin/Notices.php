<?php
/**
 * Admin notices.
 *
 * @package Citeoryx\Admin
 */

namespace Citeoryx\Admin;

use Citeoryx\Application\Search\SearchIntegrationHealth;
use Citeoryx\Core\Capabilities;

/**
 * Renders admin notices.
 */
class Notices {

	/**
	 * @var SearchIntegrationHealth
	 */
	private SearchIntegrationHealth $search_health;

	/**
	 * Constructor.
	 *
	 * @param SearchIntegrationHealth|null $search_health Search integration health store.
	 */
	public function __construct( ?SearchIntegrationHealth $search_health = null ) {
		$this->search_health = $search_health ?: new SearchIntegrationHealth();
	}

	/**
	 * Handle activation redirect.
	 *
	 * @return void
	 */
	public function activation_redirect(): void {
		if ( ! current_user_can( Capabilities::VIEW_DASHBOARD ) ) {
			return;
		}

		if ( ! get_transient( 'citeoryx_activation_redirect' ) ) {
			return;
		}

		delete_transient( 'citeoryx_activation_redirect' );

		if ( wp_doing_ajax() || is_network_admin() ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=citeoryx-dashboard' ) );
		exit;
	}

	/**
	 * Render notices.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::VIEW_DASHBOARD ) ) {
			return;
		}

		$profile = get_option( 'citeoryx_site_profile', array() );
		if ( empty( $profile ) ) {
			echo '<div class="notice notice-info is-dismissible">';
			echo '<p>' . esc_html__( 'Citeoryx is installed. Please open the plugin and complete the site profile to start scanning.', 'citeoryx' ) . '</p>';
			echo '</div>';
		}

		if ( ! current_user_can( Capabilities::MANAGE_INTEGRATIONS ) ) {
			return;
		}

		foreach ( $this->search_health->get_alerts() as $alert ) {
			echo '<div class="notice notice-error is-dismissible">';
			echo '<p>';
			echo esc_html(
				sprintf(
					/* translators: 1: provider name, 2: failure count, 3: error message. */
					__( '%1$s search data import failed %2$d consecutive times: %3$s', 'citeoryx' ),
					(string) $alert['label'],
					(int) $alert['consecutive_failures'],
					(string) $alert['message']
				)
			);
			echo '</p>';
			echo '</div>';
		}
	}
}
