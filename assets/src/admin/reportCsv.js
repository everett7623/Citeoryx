import { __ } from '@wordpress/i18n';

export const formatReportScore = ( value ) =>
	null === value || undefined === value ? '—' : `${ value }`;

const labelFor = ( value ) => value || __( '未知', 'citeoryx' );

const sourceLabels = {
	google_search_console: 'Google',
	bing_webmaster_tools: 'Bing',
};

const csvCell = ( value ) => {
	const text = String( value ?? '' );
	const safeText = /^[=+\-@\t\r]/.test( text ) ? `'${ text }` : text;
	return `"${ safeText.replace( /"/g, '""' ) }"`;
};

const dimensionRows = ( title, items = [] ) => [
	[ '', '', '' ],
	[ title, __( '点击', 'citeoryx' ), __( '展现', 'citeoryx' ) ],
	...items.map( ( item ) => [
		item.source
			? `${ item.label } (${
					sourceLabels[ item.source ] || item.source
			  })`
			: item.label,
		item.clicks,
		item.impressions,
	] ),
];

export const buildReportCsv = ( data ) => {
	const dimensions = data.performance?.dimensions || {};
	const rows = [
		[ __( '报告生成时间', 'citeoryx' ), data.generated_at ],
		[ __( '内容资产总数', 'citeoryx' ), data.content.total ],
		[
			__( '平均健康分', 'citeoryx' ),
			formatReportScore( data.content.average_health_score ),
		],
		[
			__( '平均 AI 准备度', 'citeoryx' ),
			formatReportScore( data.content.average_ai_readiness_score ),
		],
		[ __( '待处理问题总数', 'citeoryx' ), data.issues.open_total ],
		[ __( '28 天搜索点击', 'citeoryx' ), data.performance?.clicks ?? '' ],
		[
			__( '28 天搜索展现', 'citeoryx' ),
			data.performance?.impressions ?? '',
		],
		[
			__( '最近搜索数据日期', 'citeoryx' ),
			data.performance?.last_imported_date ?? '',
		],
		[ '', '' ],
		[ __( '内容状态', 'citeoryx' ), __( '数量', 'citeoryx' ) ],
		...data.content.status_counts.map( ( item ) => [
			labelFor( item.label ),
			item.count,
		] ),
		[ '', '' ],
		[ __( '问题严重度', 'citeoryx' ), __( '数量', 'citeoryx' ) ],
		...data.issues.severity_counts.map( ( item ) => [
			labelFor( item.label ),
			item.count,
		] ),
		[ '', '' ],
		[ __( '问题分类', 'citeoryx' ), __( '数量', 'citeoryx' ) ],
		...data.issues.category_counts.map( ( item ) => [
			labelFor( item.label ),
			item.count,
		] ),
		...dimensionRows( __( '热门查询', 'citeoryx' ), dimensions.queries ),
		...dimensionRows( __( '国家', 'citeoryx' ), dimensions.countries ),
		...dimensionRows( __( '设备', 'citeoryx' ), dimensions.devices ),
	];

	return rows.map( ( row ) => row.map( csvCell ).join( ',' ) ).join( '\r\n' );
};

export const exportReportCsv = ( data ) => {
	const blob = new Blob( [ `\uFEFF${ buildReportCsv( data ) }` ], {
		type: 'text/csv;charset=utf-8',
	} );
	const url = URL.createObjectURL( blob );
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = `citeoryx-report-${ new Date()
		.toISOString()
		.slice( 0, 10 ) }.csv`;
	link.click();
	URL.revokeObjectURL( url );
};
