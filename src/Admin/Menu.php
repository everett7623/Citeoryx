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
			Capabilities::VIEW_DASHBOARD,
			'citeoryx-dashboard',
			array( $this, 'render_app' ),
			'dashicons-chart-area',
			25
		);

		$submenus = array(
			'dashboard'        => __( '总览', 'citeoryx' ),
			'inventory'        => __( '内容资产', 'citeoryx' ),
			'opportunities'      => __( '问题与机会', 'citeoryx' ),
			'optimizer'          => __( '优化工作台', 'citeoryx' ),
			'ai-discoverability' => __( 'AI 可发现性', 'citeoryx' ),
			'planning'           => __( '内容规划', 'citeoryx' ),
			'reports'            => __( '报告', 'citeoryx' ),
			'settings'           => __( '设置', 'citeoryx' ),
		);

		$first = true;
		foreach ( $submenus as $slug => $label ) {
			add_submenu_page(
				'citeoryx-dashboard',
				$label,
				$label,
				Capabilities::VIEW_DASHBOARD,
				'citeoryx-dashboard#/'. $slug,
				$first ? array( $this, 'render_app' ) : '__return_null'
			);
			$first = false;
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
