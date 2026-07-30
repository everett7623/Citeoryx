import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { getApiErrorMessage } from '../apiError';
import {
	Card,
	CardBody,
	CardHeader,
	Button,
	Spinner,
	Notice,
	Placeholder,
} from '@wordpress/components';
import { postList } from '@wordpress/icons';

const Dashboard = () => {
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ scanId, setScanId ] = useState( null );
	const [ scanRun, setScanRun ] = useState( null );

	const fetchDashboard = () => {
		setLoading( true );
		apiFetch( { path: 'citeoryx/v1/dashboard' } )
			.then( ( response ) => {
				setData( response.data );
				setError( null );
				const activeScan = ( response.data.recent_scans || [] ).find(
					( scan ) => [ 'queued', 'running' ].includes( scan.status )
				);
				setScanId( activeScan?.id || null );
				setScanRun( activeScan || null );
			} )
			.catch( ( err ) =>
				setError(
					getApiErrorMessage(
						err,
						__( 'Failed to load dashboard.', 'citeoryx' )
					)
				)
			)
			.finally( () => setLoading( false ) );
	};

	useEffect( () => {
		fetchDashboard();
	}, [] );

	useEffect( () => {
		if ( ! scanId ) {
			return undefined;
		}

		let cancelled = false;
		let timer;
		const poll = () => {
			apiFetch( { path: `citeoryx/v1/scans/${ scanId }` } )
				.then( ( response ) => {
					if ( cancelled ) {
						return;
					}
					const run = response.data;
					setScanRun( run );
					if (
						[ 'completed', 'failed', 'cancelled' ].includes(
							run.status
						)
					) {
						setScanId( null );
						setScanRun( null );
						setLoading( false );
						if ( 'failed' === run.status ) {
							setError(
								run.error_log || __( '扫描失败。', 'citeoryx' )
							);
						}
						fetchDashboard();
						return;
					}
					timer = setTimeout( poll, 3000 );
				} )
				.catch( ( err ) => {
					if ( ! cancelled ) {
						setError(
							getApiErrorMessage(
								err,
								__( '无法读取扫描进度。', 'citeoryx' )
							)
						);
						setScanId( null );
						setScanRun( null );
						setLoading( false );
					}
				} );
		};
		poll();

		return () => {
			cancelled = true;
			if ( timer ) {
				clearTimeout( timer );
			}
		};
	}, [ scanId ] );

	const runScan = () => {
		setLoading( true );
		apiFetch( {
			path: 'citeoryx/v1/scans',
			method: 'POST',
			data: { scan_type: 'incremental' },
		} )
			.then( ( response ) => {
				setError( null );
				setScanId( response.data.id );
				setScanRun( response.data );
			} )
			.catch( ( err ) => {
				setError(
					getApiErrorMessage( err, __( 'Scan failed.', 'citeoryx' ) )
				);
				setLoading( false );
			} );
	};

	if ( loading && ! data ) {
		return <Spinner />;
	}

	// 空状态：首次使用，无内容
	if ( ! loading && data && data.total_content === 0 ) {
		return (
			<div className="citeoryx-dashboard">
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }
				{ scanRun && (
					<Notice status="info" isDismissible={ false }>
						{ __( '扫描任务正在后台运行：', 'citeoryx' ) }{ ' ' }
						{ scanRun.processed_items } / { scanRun.total_items || '—' }
					</Notice>
				) }
				<Placeholder
					icon={ postList }
					label={ __( '欢迎使用 Citeoryx', 'citeoryx' ) }
					instructions={ __(
						'点击下方"开始扫描"按钮进行首次内容盘点，Citeoryx 将自动识别站点内容并生成健康报告。',
						'citeoryx'
					) }
				>
					<Button
						variant="primary"
						onClick={ runScan }
						disabled={ Boolean( loading || scanId ) }
					>
						{ loading || scanId
							? __( '扫描中…', 'citeoryx' )
							: __( '开始扫描', 'citeoryx' ) }
					</Button>
				</Placeholder>
			</div>
		);
	}

	return (
		<div className="citeoryx-dashboard">
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ scanRun && (
				<Notice status="info" isDismissible={ false }>
					{ __( '扫描任务正在后台运行：', 'citeoryx' ) }{ ' ' }
					{ scanRun.processed_items } / { scanRun.total_items || '—' }
				</Notice>
			) }
			<div className="citeoryx-dashboard__actions">
				<Button
					variant="primary"
					onClick={ runScan }
					disabled={ Boolean( loading || scanId ) }
				>
					{ loading || scanId
						? __( '扫描中…', 'citeoryx' )
						: __( '运行增量扫描', 'citeoryx' ) }
				</Button>
				<Button
					onClick={ fetchDashboard }
					disabled={ Boolean( loading || scanId ) }
				>
					{ __( '刷新', 'citeoryx' ) }
				</Button>
			</div>
			{ data && (
				<>
					<Card>
						<CardHeader>
							{ __( '内容健康度概览', 'citeoryx' ) }
						</CardHeader>
						<CardBody>
							<div className="citeoryx-stat-grid">
								<div className="citeoryx-stat">
									<span className="citeoryx-stat__value">
										{ data.total_content }
									</span>
									<span className="citeoryx-stat__label">
										{ __( '内容资产', 'citeoryx' ) }
									</span>
								</div>
								{ Object.entries( data.status_counts ).map(
									( [ status, count ] ) => (
										<div
											className="citeoryx-stat"
											key={ status }
										>
											<span className="citeoryx-stat__value">
												{ count }
											</span>
											<span className="citeoryx-stat__label">
												{ status }
											</span>
										</div>
									)
								) }
							</div>
						</CardBody>
					</Card>

					<Card>
						<CardHeader>
							{ __( '高优先级问题', 'citeoryx' ) }
						</CardHeader>
						<CardBody>
							{ data.high_priority.length === 0 && (
								<p>
									{ __( '暂无高优先级问题。', 'citeoryx' ) }
								</p>
							) }
							<ul className="citeoryx-issue-list">
								{ data.high_priority.map( ( issue ) => (
									<li key={ issue.id }>
										<strong>{ issue.issue_code }</strong>:{ ' ' }
										{ issue.title }
										{ issue.priority_score && (
											<span className="citeoryx-priority">
												{ issue.priority_score }
											</span>
										) }
									</li>
								) ) }
							</ul>
						</CardBody>
					</Card>
				</>
			) }
		</div>
	);
};

export default Dashboard;
