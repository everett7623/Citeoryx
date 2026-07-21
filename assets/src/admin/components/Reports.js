import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { getApiErrorMessage } from '../apiError';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	Spinner,
} from '@wordpress/components';
import {
	DistributionTable,
	RecentScansTable,
	TopIssuesTable,
} from './ReportTables';
import { exportReportCsv, formatReportScore } from '../reportCsv';

const Reports = () => {
	const canExport = Boolean( window.citeoryxAdmin?.user?.canExport );
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	const fetchReport = () => {
		setLoading( true );
		setError( null );
		apiFetch( { path: 'citeoryx/v1/reports/summary' } )
			.then( ( response ) => setData( response.data ) )
			.catch( ( err ) =>
				setError(
					getApiErrorMessage(
						err,
						__( '无法加载报告。', 'citeoryx' )
					)
				)
			)
			.finally( () => setLoading( false ) );
	};

	useEffect( () => {
		fetchReport();
	}, [] );

	if ( loading && ! data ) {
		return <Spinner />;
	}

	return (
		<div className="citeoryx-reports">
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<div className="citeoryx-dashboard__actions">
				<Button onClick={ fetchReport } disabled={ loading }>
					{ loading
						? __( '刷新中…', 'citeoryx' )
						: __( '刷新报告', 'citeoryx' ) }
				</Button>
				{ canExport && (
					<Button
						variant="secondary"
						onClick={ () => exportReportCsv( data ) }
						disabled={ ! data }
					>
						{ __( '导出 CSV', 'citeoryx' ) }
					</Button>
				) }
			</div>
			{ data && (
				<>
					<Card>
						<CardHeader>
							{ __( '站点摘要', 'citeoryx' ) }
						</CardHeader>
						<CardBody>
							<div className="citeoryx-stat-grid">
								<div className="citeoryx-stat">
									<span className="citeoryx-stat__value">
										{ data.content.total }
									</span>
									<span className="citeoryx-stat__label">
										{ __( '内容资产', 'citeoryx' ) }
									</span>
								</div>
								<div className="citeoryx-stat">
									<span className="citeoryx-stat__value">
										{ formatReportScore(
											data.content.average_health_score
										) }
									</span>
									<span className="citeoryx-stat__label">
										{ __( '平均健康分', 'citeoryx' ) }
									</span>
								</div>
								<div className="citeoryx-stat">
									<span className="citeoryx-stat__value">
										{ formatReportScore(
											data.content
												.average_ai_readiness_score
										) }
									</span>
									<span className="citeoryx-stat__label">
										{ __( '平均 AI 准备度', 'citeoryx' ) }
									</span>
								</div>
								<div className="citeoryx-stat">
									<span className="citeoryx-stat__value">
										{ data.issues.open_total }
									</span>
									<span className="citeoryx-stat__label">
										{ __( '待处理问题', 'citeoryx' ) }
									</span>
								</div>
								<div className="citeoryx-stat">
									<span className="citeoryx-stat__value">
										{ data.performance?.clicks ?? '—' }
									</span>
									<span className="citeoryx-stat__label">
										{ __( '28 天搜索点击', 'citeoryx' ) }
									</span>
								</div>
								<div className="citeoryx-stat">
									<span className="citeoryx-stat__value">
										{ data.performance?.impressions ?? '—' }
									</span>
									<span className="citeoryx-stat__label">
										{ __( '28 天搜索展现', 'citeoryx' ) }
									</span>
								</div>
							</div>
						</CardBody>
					</Card>
					<div className="citeoryx-reports__grid">
						<DistributionTable
							title={ __( '内容状态', 'citeoryx' ) }
							items={ data.content.status_counts }
							emptyLabel={ __(
								'暂无内容状态数据。',
								'citeoryx'
							) }
						/>
						<DistributionTable
							title={ __( '问题严重度', 'citeoryx' ) }
							items={ data.issues.severity_counts }
							emptyLabel={ __( '暂无待处理问题。', 'citeoryx' ) }
						/>
						<DistributionTable
							title={ __( '问题分类', 'citeoryx' ) }
							items={ data.issues.category_counts }
							emptyLabel={ __(
								'暂无问题分类数据。',
								'citeoryx'
							) }
						/>
					</div>
					<div className="citeoryx-reports__grid">
						<TopIssuesTable items={ data.issues.top_items } />
						<RecentScansTable items={ data.scans.recent } />
					</div>
					<p className="citeoryx-reports__meta">
						{ __( '生成时间：', 'citeoryx' ) }
						{ data.generated_at }
						{ ' · ' }
						{ __( 'SEO 插件：', 'citeoryx' ) }
						{ data.plugin.seo_plugin ||
							__( '未检测到', 'citeoryx' ) }
					</p>
				</>
			) }
		</div>
	);
};

export default Reports;
