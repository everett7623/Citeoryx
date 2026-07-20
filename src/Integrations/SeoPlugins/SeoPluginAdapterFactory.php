<?php
/**
 * SEO plugin adapter factory.
 *
 * @package Citeoryx\Integrations\SeoPlugins
 */

namespace Citeoryx\Integrations\SeoPlugins;

/**
 * Detects and creates the active SEO plugin adapter.
 */
class SeoPluginAdapterFactory {

	/**
	 * Adapter registry.
	 *
	 * @var array<string, SeoPluginAdapterInterface>
	 */
	private array $adapters;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->adapters = array(
			'rank-math' => new RankMathAdapter(),
			'yoast'     => new YoastAdapter(),
			'aioseo'    => new AioseoAdapter(),
			'seopress'  => new SeopressAdapter(),
		);
	}

	/**
	 * Get active adapter.
	 *
	 * @return SeoPluginAdapterInterface
	 */
	public function active(): SeoPluginAdapterInterface {
		foreach ( $this->adapters as $adapter ) {
			if ( $adapter->is_active() ) {
				return $adapter;
			}
		}

		return new NullAdapter();
	}

	/**
	 * Detect active plugin slug.
	 *
	 * @return string|null
	 */
	public function detect(): ?string {
		foreach ( $this->adapters as $slug => $adapter ) {
			if ( $adapter->is_active() ) {
				return $slug;
			}
		}

		return null;
	}

	/**
	 * Get all adapter instances.
	 *
	 * @return array<string, SeoPluginAdapterInterface>
	 */
	public function all(): array {
		return $this->adapters;
	}
}
