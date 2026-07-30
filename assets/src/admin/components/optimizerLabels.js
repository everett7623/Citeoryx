import { __ } from '@wordpress/i18n';

export const optimizerCategoryLabel = ( category ) => {
	const labels = {
		content: __( '内容', 'citeoryx' ),
		structure: __( '结构', 'citeoryx' ),
		links: __( '链接', 'citeoryx' ),
		seo: __( 'SEO', 'citeoryx' ),
		discoverability: __( '可发现性', 'citeoryx' ),
		aeo: __( 'AI 可发现性', 'citeoryx' ),
		general: __( '通用', 'citeoryx' ),
	};
	return labels[ category ] || category;
};

export const confidenceLabel = ( confidence ) => {
	const labels = {
		low: __( '低', 'citeoryx' ),
		medium: __( '中', 'citeoryx' ),
		high: __( '高', 'citeoryx' ),
	};
	return labels[ confidence ] || __( '未知', 'citeoryx' );
};
