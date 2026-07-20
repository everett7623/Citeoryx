import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Card, CardBody, CardHeader, Button, Spinner, SelectControl } from '@wordpress/components';

const Issues = () => {
	const [ issues, setIssues ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ page, setPage ] = useState( 1 );
	const [ loading, setLoading ] = useState( true );
	const [ status, setStatus ] = useState( 'open' );

	const fetchIssues = ( currentPage = page ) => {
		setLoading( true );
		const query = new URLSearchParams();
		query.set( 'page', currentPage );
		query.set( 'per_page', '20' );
		query.set( 'status', status );

		apiFetch( { path: `citeoryx/v1/issues?${ query.toString() }` } )
			.then( ( response ) => {
				setIssues( response.data.items );
				setTotal( response.data.total );
			} )
			.catch( () => setIssues( [] ) )
			.finally( () => setLoading( false ) );
	};

	useEffect( () => {
		fetchIssues();
	}, [ page, status ] );

	const resolveIssue = ( id ) => {
		apiFetch( {
			path: `citeoryx/v1/issues/${ id }`,
			method: 'PATCH',
			data: { status: 'resolved' },
		} ).then( () => fetchIssues() );
	};

	const totalPages = Math.ceil( total / 20 );

	const exportCSV = () => {
		const headers = [ 'ID', 'Issue Code', 'Title', 'Category', 'Severity', 'Priority Score', 'Status' ];
		const rows = issues.map( ( issue ) => [
			issue.id,
			issue.issue_code,
			issue.title,
			issue.category,
			issue.severity,
			issue.priority_score ?? '',
			issue.status,
		] );
		const csv = [ headers, ...rows ].map( ( row ) => row.map( ( cell ) => `"${ String( cell ).replace( /"/g, '""' ) }"` ).join( ',' ) ).join( '\n' );
		const blob = new Blob( [ csv ], { type: 'text/csv;charset=utf-8;' } );
		const url = URL.createObjectURL( blob );
		const link = document.createElement( 'a' );
		link.href = url;
		link.download = `citeoryx-issues-${ status }-page-${ page }.csv`;
		link.click();
		URL.revokeObjectURL( url );
	};

	return (
		<div className="citeoryx-issues">
			<Card>
				<CardHeader>{ __( '问题与机会', 'citeoryx' ) }</CardHeader>
				<CardBody>
					<div className="citeoryx-filters">
						<SelectControl
							label={ __( '状态', 'citeoryx' ) }
							value={ status }
							options={ [
								{ label: __( 'Open', 'citeoryx' ), value: 'open' },
								{ label: __( 'Resolved', 'citeoryx' ), value: 'resolved' },
								{ label: __( 'Ignored', 'citeoryx' ), value: 'ignored' },
							] }
							onChange={ ( value ) => { setStatus( value ); setPage( 1 ); } }
						/>
						<Button variant="secondary" onClick={ exportCSV } disabled={ loading || issues.length === 0 }>
							{ __( '导出 CSV', 'citeoryx' ) }
						</Button>
					</div>

					{ loading && <Spinner /> }

					<table className="wp-list-table widefat fixed striped table-view-list">
						<thead>
							<tr>
								<th>{ __( '问题代码', 'citeoryx' ) }</th>
								<th>{ __( '标题', 'citeoryx' ) }</th>
								<th>{ __( '分类', 'citeoryx' ) }</th>
								<th>{ __( '严重度', 'citeoryx' ) }</th>
								<th>{ __( '优先级', 'citeoryx' ) }</th>
								<th>{ __( '操作', 'citeoryx' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ issues.map( ( issue ) => (
								<tr key={ issue.id }>
									<td>{ issue.issue_code }</td>
									<td>{ issue.title }</td>
									<td>{ issue.category }</td>
									<td>{ issue.severity }</td>
									<td>{ issue.priority_score ?? '-' }</td>
									<td>
										{ status === 'open' && (
											<Button size="small" onClick={ () => resolveIssue( issue.id ) }>
												{ __( '解决', 'citeoryx' ) }
											</Button>
										) }
									</td>
								</tr>
							) ) }
						</tbody>
					</table>

					<div className="citeoryx-pagination">
						<Button disabled={ page <= 1 } onClick={ () => setPage( page - 1 ) }>
							{ __( '上一页', 'citeoryx' ) }
						</Button>
						<span>{ page } / { totalPages || 1 }</span>
						<Button disabled={ page >= totalPages } onClick={ () => setPage( page + 1 ) }>
							{ __( '下一页', 'citeoryx' ) }
						</Button>
					</div>
				</CardBody>
			</Card>
		</div>
	);
};

export default Issues;
