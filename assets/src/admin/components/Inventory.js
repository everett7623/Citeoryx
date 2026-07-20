import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Card, CardBody, CardHeader, Button, Spinner, TextControl, SelectControl } from '@wordpress/components';

const Inventory = () => {
	const [ items, setItems ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ page, setPage ] = useState( 1 );
	const [ loading, setLoading ] = useState( true );
	const [ filters, setFilters ] = useState( { status: '', post_type: '', search: '' } );

	const fetchItems = ( currentPage = page ) => {
		setLoading( true );
		const query = new URLSearchParams();
		query.set( 'page', currentPage );
		query.set( 'per_page', '20' );
		if ( filters.status ) query.set( 'status', filters.status );
		if ( filters.post_type ) query.set( 'post_type', filters.post_type );
		if ( filters.search ) query.set( 'search', filters.search );

		apiFetch( { path: `citeoryx/v1/content?${ query.toString() }` } )
			.then( ( response ) => {
				setItems( response.data.items );
				setTotal( response.data.total );
			} )
			.catch( () => setItems( [] ) )
			.finally( () => setLoading( false ) );
	};

	useEffect( () => {
		fetchItems();
	}, [ page, filters.status, filters.post_type ] );

	const applySearch = () => fetchItems( 1 );
	const totalPages = Math.ceil( total / 20 );

	const exportCSV = () => {
		const headers = [ 'ID', 'URL', 'Post Type', 'Status', 'Health Score', 'AI Readiness Score', 'Modified At' ];
		const rows = items.map( ( item ) => [
			item.id,
			item.canonical_url,
			item.post_type,
			item.status,
			item.health_score ?? '',
			item.ai_readiness_score ?? '',
			item.modified_at,
		] );
		const csv = [ headers, ...rows ].map( ( row ) => row.map( ( cell ) => `"${ String( cell ).replace( /"/g, '""' ) }"` ).join( ',' ) ).join( '\n' );
		const blob = new Blob( [ csv ], { type: 'text/csv;charset=utf-8;' } );
		const url = URL.createObjectURL( blob );
		const link = document.createElement( 'a' );
		link.href = url;
		link.download = `citeoryx-inventory-page-${ page }.csv`;
		link.click();
		URL.revokeObjectURL( url );
	};

	return (
		<div className="citeoryx-inventory">
			<Card>
				<CardHeader>{ __( '内容资产', 'citeoryx' ) }</CardHeader>
				<CardBody>
					<div className="citeoryx-filters">
						<SelectControl
							label={ __( '状态', 'citeoryx' ) }
							value={ filters.status }
							options={ [
								{ label: __( '全部', 'citeoryx' ), value: '' },
								{ label: __( 'Healthy', 'citeoryx' ), value: 'healthy' },
								{ label: __( 'Decaying', 'citeoryx' ), value: 'decaying' },
								{ label: __( 'Orphaned', 'citeoryx' ), value: 'orphaned' },
								{ label: __( 'Opportunity', 'citeoryx' ), value: 'opportunity' },
								{ label: __( 'Stale', 'citeoryx' ), value: 'stale' },
							] }
							onChange={ ( value ) => { setFilters( { ...filters, status: value } ); setPage( 1 ); } }
						/>
						<TextControl
							label={ __( '搜索 URL', 'citeoryx' ) }
							value={ filters.search }
							onChange={ ( value ) => setFilters( { ...filters, search: value } ) }
							onKeyDown={ ( e ) => e.key === 'Enter' && applySearch() }
						/>
						<Button variant="primary" onClick={ applySearch }>
							{ __( '搜索', 'citeoryx' ) }
						</Button>
						<Button variant="secondary" onClick={ exportCSV } disabled={ loading || items.length === 0 }>
							{ __( '导出 CSV', 'citeoryx' ) }
						</Button>
					</div>

					{ loading && <Spinner /> }

					<table className="wp-list-table widefat fixed striped table-view-list">
						<thead>
							<tr>
								<th>{ __( 'URL', 'citeoryx' ) }</th>
								<th>{ __( '类型', 'citeoryx' ) }</th>
								<th>{ __( '状态', 'citeoryx' ) }</th>
								<th>{ __( '健康分', 'citeoryx' ) }</th>
								<th>{ __( 'AI 准备度', 'citeoryx' ) }</th>
								<th>{ __( '更新于', 'citeoryx' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ items.map( ( item ) => (
								<tr key={ item.id }>
									<td><a href={ item.canonical_url } target="_blank" rel="noopener noreferrer">{ item.canonical_url }</a></td>
									<td>{ item.post_type }</td>
									<td>{ item.status }</td>
									<td>{ item.health_score ?? '-' }</td>
									<td>{ item.ai_readiness_score ?? '-' }</td>
									<td>{ item.modified_at }</td>
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

export default Inventory;
