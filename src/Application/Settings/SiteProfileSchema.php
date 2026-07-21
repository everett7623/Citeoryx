<?php
/**
 * Site profile schema.
 *
 * @package Citeoryx\Application\Settings
 */

namespace Citeoryx\Application\Settings;

/**
 * Defines, sanitizes, and validates the onboarding site profile.
 */
class SiteProfileSchema {

	/**
	 * Get profile options and defaults for the admin UI.
	 *
	 * @return array<string, mixed>
	 */
	public function options(): array {
		$content_types         = $this->content_types();
		$content_type_values   = array_column( $content_types, 'value' );
		$default_content_types = array_values( array_intersect( array( 'post', 'page' ), $content_type_values ) );

		if ( empty( $default_content_types ) && ! empty( $content_type_values ) ) {
			$default_content_types[] = $content_type_values[0];
		}

		return array(
			'site_types'     => $this->format_options( $this->site_types() ),
			'primary_goals'  => $this->format_options( $this->primary_goals() ),
			'content_types'  => $content_types,
			'update_rhythms' => $this->format_options( $this->update_rhythms() ),
			'risk_levels'    => $this->format_options( $this->risk_levels() ),
			'review_cycles'  => $this->format_options( $this->review_cycles() ),
			'defaults'       => array(
				'site_type'          => '',
				'primary_goal'       => '',
				'core_content_types' => $default_content_types,
				'main_language'      => get_locale(),
				'main_region'        => __( '全球', 'citeoryx' ),
				'update_rhythm'      => 'monthly',
				'risk_level'         => 'standard',
				'review_cycle_days'  => 90,
			),
		);
	}

	/**
	 * Sanitize a profile without assuming that it is complete.
	 *
	 * @param mixed $profile Raw profile.
	 * @return array<string, mixed>
	 */
	public function sanitize( $profile ): array {
		if ( ! is_array( $profile ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( array( 'site_type', 'primary_goal', 'main_language', 'main_region', 'update_rhythm', 'risk_level' ) as $key ) {
			if ( isset( $profile[ $key ] ) && is_scalar( $profile[ $key ] ) ) {
				$sanitized[ $key ] = sanitize_text_field( (string) $profile[ $key ] );
			}
		}

		if ( isset( $profile['core_content_types'] ) && is_array( $profile['core_content_types'] ) ) {
			$sanitized['core_content_types'] = array_values(
				array_unique(
					array_filter(
						array_map(
							static fn ( $value ): string => is_scalar( $value ) ? sanitize_key( (string) $value ) : '',
							$profile['core_content_types']
						)
					)
				)
			);
		}

		if ( isset( $profile['review_cycle_days'] ) && is_scalar( $profile['review_cycle_days'] ) ) {
			$sanitized['review_cycle_days'] = (int) $profile['review_cycle_days'];
		}

		return $sanitized;
	}

	/**
	 * Return a user-facing validation error, or an empty string when valid.
	 *
	 * @param array<string, mixed> $profile Sanitized profile.
	 * @return string
	 */
	public function validation_error( array $profile ): string {
		$required = array( 'site_type', 'primary_goal', 'main_language', 'main_region', 'update_rhythm', 'risk_level', 'review_cycle_days' );
		foreach ( $required as $key ) {
			if ( ! isset( $profile[ $key ] ) || '' === (string) $profile[ $key ] ) {
				return __( '请完成所有站点画像字段。', 'citeoryx' );
			}
		}

		if ( empty( $profile['core_content_types'] ) || ! is_array( $profile['core_content_types'] ) ) {
			return __( '请至少选择一种核心内容类型。', 'citeoryx' );
		}

		$enum_fields = array(
			'site_type'         => $this->site_types(),
			'primary_goal'      => $this->primary_goals(),
			'update_rhythm'     => $this->update_rhythms(),
			'risk_level'        => $this->risk_levels(),
			'review_cycle_days' => $this->review_cycles(),
		);

		foreach ( $enum_fields as $key => $allowed ) {
			if ( ! array_key_exists( $profile[ $key ], $allowed ) ) {
				return __( '站点画像包含无效选项，请刷新页面后重试。', 'citeoryx' );
			}
		}

		$allowed_content_types = array_column( $this->content_types(), 'value' );
		foreach ( $profile['core_content_types'] as $post_type ) {
			if ( ! in_array( $post_type, $allowed_content_types, true ) ) {
				return __( '核心内容类型已失效，请重新选择。', 'citeoryx' );
			}
		}

		return '';
	}

	/**
	 * Determine whether a stored profile is complete and valid.
	 *
	 * @param mixed $profile Raw profile.
	 * @return bool
	 */
	public function is_complete( $profile ): bool {
		return '' === $this->validation_error( $this->sanitize( $profile ) );
	}

	/**
	 * Get public content types that have an admin UI.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	private function content_types(): array {
		$post_types = get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'objects'
		);
		$options    = array();

		foreach ( $post_types as $post_type ) {
			if ( 'attachment' === $post_type->name ) {
				continue;
			}
			$options[] = array(
				'value' => $post_type->name,
				'label' => $post_type->labels->singular_name ?: $post_type->label,
			);
		}

		return $options;
	}

	/** @return array<string, string> */
	private function site_types(): array {
		return array(
			'blog'      => __( '博客', 'citeoryx' ),
			'corporate' => __( '企业站', 'citeoryx' ),
			'shop'      => __( '商城', 'citeoryx' ),
			'media'     => __( '媒体', 'citeoryx' ),
			'docs'      => __( '文档 / 知识库', 'citeoryx' ),
			'local'     => __( '本地服务', 'citeoryx' ),
		);
	}

	/** @return array<string, string> */
	private function primary_goals(): array {
		return array(
			'traffic'       => __( '流量', 'citeoryx' ),
			'leads'         => __( '询盘', 'citeoryx' ),
			'sales'         => __( '销售', 'citeoryx' ),
			'subscriptions' => __( '订阅', 'citeoryx' ),
			'brand'         => __( '品牌曝光', 'citeoryx' ),
			'support'       => __( '支持分流', 'citeoryx' ),
		);
	}

	/** @return array<string, string> */
	private function update_rhythms(): array {
		return array(
			'high_frequency' => __( '高频', 'citeoryx' ),
			'weekly'         => __( '周更', 'citeoryx' ),
			'monthly'        => __( '月更', 'citeoryx' ),
			'evergreen'      => __( '低频常青', 'citeoryx' ),
		);
	}

	/** @return array<string, string> */
	private function risk_levels(): array {
		return array(
			'standard' => __( '普通', 'citeoryx' ),
			'ymyl'     => 'YMYL',
			'medical'  => __( '医疗', 'citeoryx' ),
			'finance'  => __( '金融', 'citeoryx' ),
			'legal'    => __( '法律', 'citeoryx' ),
		);
	}

	/** @return array<int, string> */
	private function review_cycles(): array {
		return array(
			30  => __( '30 天', 'citeoryx' ),
			90  => __( '90 天', 'citeoryx' ),
			180 => __( '180 天', 'citeoryx' ),
			365 => __( '365 天', 'citeoryx' ),
		);
	}

	/**
	 * Convert a value-label map to REST option objects.
	 *
	 * @param array<int|string, string> $options Value-label map.
	 * @return array<int, array{value: int|string, label: string}>
	 */
	private function format_options( array $options ): array {
		$result = array();
		foreach ( $options as $value => $label ) {
			$result[] = array(
				'value' => $value,
				'label' => $label,
			);
		}
		return $result;
	}
}
