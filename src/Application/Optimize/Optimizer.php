<?php
/**
 * Content optimizer service.
 *
 * @package Citeoryx\Application\Optimize
 */

namespace Citeoryx\Application\Optimize;

use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Domain\Issue\IssueRepository;
use Citeoryx\Application\Analyze\HealthScorer;
use Citeoryx\Application\Analyze\AiReadinessScorer;

/**
 * Generates actionable optimization recommendations for a content item.
 */
class Optimizer {

	private ContentRepository $content_repo;
	private IssueRepository $issue_repo;
	private HealthScorer $health_scorer;
	private AiReadinessScorer $aeo_scorer;
	private InternalLinkSuggester $link_suggester;
	private RevisionPerformanceMonitor $performance_monitor;

	public function __construct(
		ContentRepository $content_repo,
		IssueRepository $issue_repo,
		HealthScorer $health_scorer,
		AiReadinessScorer $aeo_scorer,
		InternalLinkSuggester $link_suggester,
		RevisionPerformanceMonitor $performance_monitor
	) {
		$this->content_repo   = $content_repo;
		$this->issue_repo     = $issue_repo;
		$this->health_scorer  = $health_scorer;
		$this->aeo_scorer     = $aeo_scorer;
		$this->link_suggester = $link_suggester;
		$this->performance_monitor = $performance_monitor;
	}

	/**
	 * Get recommendations for a content item.
	 *
	 * @param int $content_id Content ID.
	 * @return array<string, mixed>
	 */
	public function get_recommendations( int $content_id ): array {
		$item = $this->content_repo->find( $content_id );
		if ( ! $item ) {
			return array(
				'error' => __( 'Content item not found.', 'citeoryx' ),
			);
		}

		$issues = $this->issue_repo->list(
			array(
				'content_id' => $content_id,
				'status'     => 'open',
			),
			1,
			100
		);
		$issues = $issues['items'];
		$meta   = $item->metadata;

		$health = $this->health_scorer->score( $item );
		$aeo    = $this->aeo_scorer->score( $item );

		$recommendations = array();

		foreach ( $issues as $issue ) {
			$recommendations[] = $this->map_issue_to_recommendation( $issue );
		}

		$word_count = $meta['word_count'] ?? 0;
		if ( $word_count < 300 ) {
			$recommendations[] = array(
				'category'    => 'content',
				'priority'    => 'high',
				'title'       => __( '扩展内容深度', 'citeoryx' ),
				'description' => __( '当前内容较短，建议补充更多细节、示例或相关背景，达到 500+ 字。', 'citeoryx' ),
				'action'      => __( '编辑内容', 'citeoryx' ),
			);
		}

		$headings      = $meta['headings'] ?? array();
		$heading_count = is_array( $headings ) ? array_sum( $headings ) : 0;
		if ( $heading_count < 2 ) {
			$recommendations[] = array(
				'category'    => 'structure',
				'priority'    => 'medium',
				'title'       => __( '增加章节标题', 'citeoryx' ),
				'description' => __( '使用 H2/H3 标题组织内容，提高可读性和 AI 抓取效率。', 'citeoryx' ),
				'action'      => __( '编辑内容', 'citeoryx' ),
			);
		}

		$internal_links = $meta['internal_links'] ?? 0;
		if ( $internal_links < 2 ) {
			$recommendations[] = array(
				'category'    => 'links',
				'priority'    => 'medium',
				'title'       => __( '添加内链', 'citeoryx' ),
				'description' => __( '建议添加至少 2-3 个指向相关内容的内部链接。', 'citeoryx' ),
				'action'      => __( '编辑内容', 'citeoryx' ),
			);
		}

		return array(
			'content'          => $item->to_array(),
			'scores'           => array(
				'health' => $health,
				'aeo'    => $aeo,
			),
			'issues'           => $issues,
			'recommendations'  => $recommendations,
			'link_suggestions' => $this->link_suggester->suggest( $content_id ),
			'revision_performance' => $this->performance_monitor->get_performance( $content_id ),
		);
	}

	/**
	 * Map an issue to a recommendation.
	 *
	 * @param object $issue Issue row.
	 * @return array<string, mixed>
	 */
	private function map_issue_to_recommendation( object $issue ): array {
		$templates = array(
			'CX_INDEX_NOINDEX'            => array(
				'category' => 'discoverability',
				'title'    => __( '检查 noindex 设置', 'citeoryx' ),
				'action'   => __( '查看 SEO 设置', 'citeoryx' ),
			),
			'CX_INDEX_CANONICAL_EXTERNAL' => array(
				'category' => 'discoverability',
				'title'    => __( '修正 Canonical', 'citeoryx' ),
				'action'   => __( '编辑 SEO 设置', 'citeoryx' ),
			),
			'CX_CONTENT_STALE'            => array(
				'category' => 'content',
				'title'    => __( '更新过时内容', 'citeoryx' ),
				'action'   => __( '编辑更新', 'citeoryx' ),
			),
			'CX_CONTENT_THIN_VALUE'       => array(
				'category' => 'content',
				'title'    => __( '增强内容深度', 'citeoryx' ),
				'action'   => __( '扩展内容', 'citeoryx' ),
			),
			'CX_CONTENT_TITLE_STRUCTURE'  => array(
				'category' => 'structure',
				'title'    => __( '添加 H1 标题', 'citeoryx' ),
				'action'   => __( '编辑内容', 'citeoryx' ),
			),
			'CX_LINK_ORPHANED'            => array(
				'category' => 'links',
				'title'    => __( '增加内链引用', 'citeoryx' ),
				'action'   => __( '添加内链', 'citeoryx' ),
			),
			'CX_LINK_BROKEN_EXTERNAL'     => array(
				'category' => 'links',
				'title'    => __( '修复失效外链', 'citeoryx' ),
				'action'   => __( '检查链接', 'citeoryx' ),
			),
			'CX_AEO_EVIDENCE_MISSING'     => array(
				'category' => 'aeo',
				'title'    => __( '补充事实依据', 'citeoryx' ),
				'action'   => __( '添加引用/数据', 'citeoryx' ),
			),
			'CX_AEO_AUTHOR_UNCLEAR'       => array(
				'category' => 'aeo',
				'title'    => __( '标注作者与来源', 'citeoryx' ),
				'action'   => __( '完善作者信息', 'citeoryx' ),
			),
		);

		$template = $templates[ $issue->issue_code ] ?? array(
			'category' => 'general',
			'title'    => $issue->title,
			'action'   => __( '查看详情', 'citeoryx' ),
		);

		return array(
			'category'    => $template['category'],
			'priority'    => $issue->severity,
			'title'       => $template['title'],
			'description' => $issue->recommendation,
			'action'      => $template['action'],
			'issue_id'    => $issue->id,
			'evidence'    => $this->format_evidence( $issue->evidence ),
		);
	}

	/**
	 * Convert known issue evidence into a bounded UI-safe list.
	 *
	 * @param array<string, mixed> $evidence Raw issue evidence.
	 * @return array<int, array{label: string, value: string}>
	 */
	private function format_evidence( array $evidence ): array {
		$labels = array(
			'robots'            => __( 'Robots directives', 'citeoryx' ),
			'canonical'         => __( 'Canonical URL', 'citeoryx' ),
			'url'               => __( 'Current URL', 'citeoryx' ),
			'days_since_update' => __( 'Days since update', 'citeoryx' ),
			'word_count'        => __( 'Word count', 'citeoryx' ),
			'headings'          => __( 'Heading structure', 'citeoryx' ),
			'inbound_internal'  => __( 'Inbound internal links', 'citeoryx' ),
			'broken_count'      => __( 'Broken external links', 'citeoryx' ),
			'external_links'    => __( 'External links', 'citeoryx' ),
		);
		$items  = array();

		foreach ( $evidence as $key => $value ) {
			if ( ! is_string( $key ) || ! isset( $labels[ $key ] ) || 4 === count( $items ) ) {
				continue;
			}

			$display_value = $this->format_evidence_value( $value );
			if ( '' === $display_value ) {
				continue;
			}

			$items[] = array(
				'label' => $labels[ $key ],
				'value' => $display_value,
			);
		}

		return $items;
	}

	/**
	 * Format a primitive or short structured evidence value for the admin UI.
	 *
	 * @param mixed $value Evidence value.
	 * @return string
	 */
	private function format_evidence_value( $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? __( 'Yes', 'citeoryx' ) : __( 'No', 'citeoryx' );
		}

		if ( is_scalar( $value ) ) {
			return sanitize_text_field( (string) $value );
		}

		if ( ! is_array( $value ) ) {
			return '';
		}

		$encoded = wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return is_string( $encoded ) ? sanitize_text_field( $encoded ) : '';
	}
}
