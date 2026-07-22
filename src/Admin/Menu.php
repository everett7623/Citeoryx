<?php
/**
 * Admin menu registration.
 *
 * @package Citeoryx\Admin
 */

namespace Citeoryx\Admin;

use Citeoryx\Core\Capabilities;

/**
 * Registers admin menus.
 */
class Menu {

	/**
	 * Register menu pages.
	 *
	 * @return void
	 */
	public function register(): void {
		add_menu_page(
			__( 'Citeoryx', 'citeoryx' ),
			__( 'Citeoryx', 'citeoryx' ),
			Capabilities::VIEW_CONTENT,
			'citeoryx-dashboard',
			array( $this, 'render_app' ),
			'dashicons-chart-area',
			25
		);

		$submenus = array(
			'dashboard'    => array( __( '总览', 'citeoryx' ), Capabilities::VIEW_DASHBOARD ),
			'inventory'    => array( __( '内容资产', 'citeoryx' ), Capabilities::VIEW_CONTENT ),
			'issues'       => array( __( '问题与机会', 'citeoryx' ), Capabilities::VIEW_CONTENT ),
			'optimizer'    => array( __( '优化工作台', 'citeoryx' ), Capabilities::VIEW_CONTENT ),
			'planning'     => array( __( '内容规划', 'citeoryx' ), Capabilities::VIEW_DASHBOARD ),
			'integrations' => array( __( '集成', 'citeoryx' ), Capabilities::MANAGE_INTEGRATIONS ),
			'reports'      => array( __( '报告', 'citeoryx' ), Capabilities::VIEW_DASHBOARD ),
			'settings'     => array( __( '设置', 'citeoryx' ), Capabilities::MANAGE_SETTINGS ),
		);

		foreach ( $submenus as $slug => $menu ) {
			add_submenu_page(
				'citeoryx-dashboard',
				$menu[0],
				$menu[0],
				$menu[1],
				'citeoryx-dashboard#/' . $slug,
				array( $this, 'render_app' )
			);
		}
	}

	/**
	 * Render the React app container.
	 *
	 * @return void
	 */
	public function render_app(): void {
		echo '<div id="citeoryx-admin-app" class="wrap"></div>';
	}
}
