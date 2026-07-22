import { __ } from '@wordpress/i18n';
import { formatReportScore } from './reportCsv';

const display = ( value ) =>
	null === value || undefined === value || '' === value
		? '—'
		: String( value );

const label = ( value ) =>
	display( value )
		.replace( /_/g, ' ' )
		.replace( /\b\w/g, ( char ) => char.toUpperCase() );

const sourceLabels = {
	google_search_console: 'Google',
	bing_webmaster_tools: 'Bing',
};

const dimensionRows = ( items = [] ) =>
	items.map( ( item ) => ( {
		name: item.source
			? `${ display( item.label ) } (${
					sourceLabels[ item.source ] || item.source
			  })`
			: display( item.label ),
		clicks: display( item.clicks ),
		impressions: display( item.impressions ),
	} ) );

const countRows = ( items = [] ) =>
	items.map( ( item ) => ( {
		name: label( item.label ),
		count: display( item.count ),
	} ) );

const table = ( title, columns, rows, emptyMessage ) => ( {
	type: 'table',
	title,
	columns,
	rows,
	emptyMessage,
} );

export const buildReportSections = ( data ) => {
	const performance = data.performance || {};
	const dimensions = performance.dimensions || {};
	const countColumns = [
		{ key: 'name', label: __( '项目', 'citeoryx' ), width: 0.75 },
		{ key: 'count', label: __( '数量', 'citeoryx' ), width: 0.25 },
	];
	const dimensionColumns = [
		{ key: 'name', label: __( '维度', 'citeoryx' ), width: 0.58 },
		{ key: 'clicks', label: __( '点击', 'citeoryx' ), width: 0.21 },
		{ key: 'impressions', label: __( '展现', 'citeoryx' ), width: 0.21 },
	];

	return [
		{
			type: 'metrics',
			title: __( '站点摘要', 'citeoryx' ),
			items: [
				{
					label: __( '内容资产', 'citeoryx' ),
					value: display( data.content.total ),
				},
				{
					label: __( '平均健康分', 'citeoryx' ),
					value: formatReportScore(
						data.content.average_health_score
					),
				},
				{
					label: __( '平均 AI 准备度', 'citeoryx' ),
					value: formatReportScore(
						data.content.average_ai_readiness_score
					),
				},
				{
					label: __( '待处理问题', 'citeoryx' ),
					value: display( data.issues.open_total ),
				},
				{
					label: __( '28 天搜索点击', 'citeoryx' ),
					value: display( performance.clicks ),
				},
				{
					label: __( '28 天搜索展现', 'citeoryx' ),
					value: display( performance.impressions ),
				},
			],
		},
		table(
			__( '内容状态', 'citeoryx' ),
			countColumns,
			countRows( data.content.status_counts ),
			__( '暂无内容状态数据。', 'citeoryx' )
		),
		table(
			__( '问题严重度', 'citeoryx' ),
			countColumns,
			countRows( data.issues.severity_counts ),
			__( '暂无待处理问题。', 'citeoryx' )
		),
		table(
			__( '问题分类', 'citeoryx' ),
			countColumns,
			countRows( data.issues.category_counts ),
			__( '暂无问题分类数据。', 'citeoryx' )
		),
		table(
			__( '搜索表现趋势', 'citeoryx' ),
			[
				{ key: 'date', label: __( '日期', 'citeoryx' ), width: 0.42 },
				{ key: 'clicks', label: __( '点击', 'citeoryx' ), width: 0.29 },
				{
					key: 'impressions',
					label: __( '展现', 'citeoryx' ),
					width: 0.29,
				},
			],
			( performance.history || [] ).map( ( item ) => ( {
				date: display( item.metric_date ),
				clicks: display( item.clicks ),
				impressions: display( item.impressions ),
			} ) ),
			__( '暂无搜索表现历史。', 'citeoryx' )
		),
		table(
			__( '热门查询', 'citeoryx' ),
			dimensionColumns,
			dimensionRows( dimensions.queries ),
			__( '暂无热门查询数据。', 'citeoryx' )
		),
		table(
			__( '国家', 'citeoryx' ),
			dimensionColumns,
			dimensionRows( dimensions.countries ),
			__( '暂无国家数据。', 'citeoryx' )
		),
		table(
			__( '设备', 'citeoryx' ),
			dimensionColumns,
			dimensionRows( dimensions.devices ),
			__( '暂无设备数据。', 'citeoryx' )
		),
		table(
			__( '优先问题', 'citeoryx' ),
			[
				{ key: 'title', label: __( '问题', 'citeoryx' ), width: 0.76 },
				{
					key: 'priority',
					label: __( '优先级', 'citeoryx' ),
					width: 0.24,
				},
			],
			data.issues.top_items.map( ( issue ) => ( {
				title: display( issue.title ),
				priority: display( issue.priority_score ),
			} ) ),
			__( '暂无待处理问题。', 'citeoryx' )
		),
		table(
			__( '最近扫描', 'citeoryx' ),
			[
				{ key: 'type', label: __( '类型', 'citeoryx' ), width: 0.25 },
				{ key: 'status', label: __( '状态', 'citeoryx' ), width: 0.25 },
				{
					key: 'started',
					label: __( '开始时间', 'citeoryx' ),
					width: 0.5,
				},
			],
			data.scans.recent.map( ( scan ) => ( {
				type: label( scan.scan_type ),
				status: label( scan.status ),
				started: display( scan.started_at ),
			} ) ),
			__( '尚未运行扫描。', 'citeoryx' )
		),
	];
};
