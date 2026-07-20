<?php
/**
 * Admin notices.
 *
 * @package Citeoryx\Admin
 */

namespace Citeoryx\Admin;

use Citeoryx\Core\Capabilities;

/**
 * Renders admin notices.
 */
class Notices {

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

		wp_safe_redirect( admin_url( 'admin.php?page=citeoryx' ) );
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
	}
}
