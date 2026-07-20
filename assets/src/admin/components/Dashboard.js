import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Card, CardBody, CardHeader, Button, Spinner, Notice } from '@wordpress/components';

const Dashboard = () => {
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	const fetchDashboard = () => {
		setLoading( true );
		apiFetch( { path: 'citeoryx/v1/dashboard' } )
			.then( ( response ) => setData( response.data ) )
			.catch( ( err ) => setError( err.message || __( 'Failed to load dashboard.', 'citeoryx' ) ) )
			.finally( () => setLoading( false ) );
	};

	useEffect( () => {
		fetchDashboard();
	}, [] );

	const runScan = () => {
		setLoading( true );
		apiFetch( {
			path: 'citeoryx/v1/scans',
			method: 'POST',
			data: { scan_type: 'full' },
		} )
			.then( () => fetchDashboard() )
			.catch( ( err ) => {
				setError( err.message || __( 'Scan failed.', 'citeoryx' ) );
				setLoading( false );
			} );
	};

	if ( loading && ! data ) {
		return <Spinner />;
	}

	return (
		<div className="citeoryx-dashboard">
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<div className="citeoryx-dashboard__actions">
				<Button variant="primary" onClick={ runScan } disabled={ loading }>
					{ loading ? __( '扫描中…', 'citeoryx' ) : __( '运行增量扫描', 'citeoryx' ) }
				</Button>
				<Button onClick={ fetchDashboard } disabled={ loading }>
					{ __( '刷新', 'citeoryx' ) }
				</Button>
			</div>
			{ data && (
				<>
					<Card>
						<CardHeader>{ __( '内容健康度概览', 'citeoryx' ) }</CardHeader>
						<CardBody>
							<div className="citeoryx-stat-grid">
								<div className="citeoryx-stat">
									<span className="citeoryx-stat__value">{ data.total_content }</span>
									<span className="citeoryx-stat__label">{ __( '内容资产', 'citeoryx' ) }</span>
								</div>
								{ Object.entries( data.status_counts ).map( ( [ status, count ] ) => (
									<div className="citeoryx-stat" key={ status }>
										<span className="citeoryx-stat__value">{ count }</span>
										<span className="citeoryx-stat__label">{ status }</span>
									</div>
								) ) }
							</div>
						</CardBody>
					</Card>

					<Card>
						<CardHeader>{ __( '高优先级问题', 'citeoryx' ) }</CardHeader>
						<CardBody>
							{ data.high_priority.length === 0 && (
								<p>{ __( '暂无高优先级问题。', 'citeoryx' ) }</p>
							) }
							<ul className="citeoryx-issue-list">
								{ data.high_priority.map( ( issue ) => (
									<li key={ issue.id }>
										<strong>{ issue.issue_code }</strong>: { issue.title }
										{ issue.priority_score && (
											<span className="citeoryx-priority">{ issue.priority_score }</span>
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
