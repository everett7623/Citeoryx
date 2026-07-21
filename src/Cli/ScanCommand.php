<?php
/**
 * WP-CLI scan command.
 *
 * @package Citeoryx\Cli
 */

namespace Citeoryx\Cli;

use Citeoryx\Application\Scan\ContentScanner;
use Citeoryx\Application\Analyze\IssueEngine;
use Citeoryx\Core\Container;

if ( ! defined( 'WP_CLI' ) ) {
	return;
}

/**
 * Manages Citeoryx scans via WP-CLI.
 */
class ScanCommand extends \WP_CLI_Command {

	/**
	 * Run a full content inventory scan.
	 *
	 * ## OPTIONS
	 *
	 * [--post-type=<post-type>]
	 * : Comma-separated list of post types to scan.
	 *
	 * [--analyze]
	 * : Re-analyze all scanned items after inventory.
	 *
	 * ## EXAMPLES
	 *
	 *     wp citeoryx scan --post-type=post,page --analyze
	 *
	 * @param array<string> $args Positional args.
	 * @param array<string, mixed> $assoc_args Associative args.
	 * @return void
	 */
	public function full( array $args, array $assoc_args ): void {
		$container = new Container();
		$scanner   = $container->get( ContentScanner::class );
		$engine    = $container->get( IssueEngine::class );

		$post_types = ! empty( $assoc_args['post-type'] ) ? explode( ',', $assoc_args['post-type'] ) : array();

		\WP_CLI::log( __( 'Starting Citeoryx inventory scan...', 'citeoryx' ) );
		$count = $scanner->scan_all( $post_types );
		/* translators: %d: Number of content items scanned. */
		\WP_CLI::success( sprintf( __( 'Scanned %d content items.', 'citeoryx' ), $count ) );

		if ( ! empty( $assoc_args['analyze'] ) ) {
			\WP_CLI::log( __( 'Re-analyzing content...', 'citeoryx' ) );
			$content_repo = $container->get( \Citeoryx\Domain\Content\ContentRepository::class );
			$after_id     = 0;
			$analyzed     = 0;

			while ( true ) {
				$items = $content_repo->list_after_id( $after_id, 100 );
				if ( empty( $items ) ) {
					break;
				}
				foreach ( $items as $item ) {
					$engine->analyze( $item );
					$after_id = (int) $item->id;
					++$analyzed;
				}
			}

			/* translators: %d: Number of content items analyzed. */
			\WP_CLI::success( sprintf( __( 'Analyzed %d content items.', 'citeoryx' ), $analyzed ) );
		}
	}

	/**
	 * Analyze existing inventory.
	 *
	 * ## EXAMPLES
	 *
	 *     wp citeoryx scan analyze
	 *
	 * @param array<string> $args Positional args.
	 * @param array<string, mixed> $assoc_args Associative args.
	 * @return void
	 */
	public function analyze( array $args, array $assoc_args ): void {
		$container    = new Container();
		$engine       = $container->get( IssueEngine::class );
		$content_repo = $container->get( \Citeoryx\Domain\Content\ContentRepository::class );

		\WP_CLI::log( __( 'Starting Citeoryx analysis...', 'citeoryx' ) );
		$after_id = 0;
		$analyzed = 0;

		while ( true ) {
			$items = $content_repo->list_after_id( $after_id, 100 );
			if ( empty( $items ) ) {
				break;
			}
			foreach ( $items as $item ) {
				$engine->analyze( $item );
				$after_id = (int) $item->id;
				++$analyzed;
			}
		}

		/* translators: %d: Number of content items analyzed. */
		\WP_CLI::success( sprintf( __( 'Analyzed %d content items.', 'citeoryx' ), $analyzed ) );
	}
}
