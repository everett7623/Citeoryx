import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	SelectControl,
	Spinner,
} from '@wordpress/components';
import { getApiErrorMessage } from '../apiError';
import PlanningCalendar from './PlanningCalendar';

const typeLabels = {
	striking_distance: __( '临门一脚', 'citeoryx' ),
	refresh_before_new: __( '先更新旧内容', 'citeoryx' ),
	topic_gap_candidate: __( '主题缺口候选', 'citeoryx' ),
};

const actionLabels = {
	improve_existing: __( '增强现有页面', 'citeoryx' ),
	refresh_existing: __( '优先刷新现有页面', 'citeoryx' ),
	review_topic_gap: __( '核对意图后评估新内容', 'citeoryx' ),
};

const sourceLabels = {
	google_search_console: 'Google Search Console',
	bing_webmaster_tools: 'Bing Webmaster Tools',
};

const selectOptions = ( labels, allLabel ) => [
	{ label: allLabel, value: '' },
	...Object.entries( labels ).map( ( [ value, label ] ) => ( {
		label,
		value,
	} ) ),
];

const Planning = () => {
	const [ data, setData ] = useState( null );
	const [ filters, setFilters ] = useState( { type: '', source: '' } );
	const [ page, setPage ] = useState( 1 );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	const fetchOpportunities = useCallback( () => {
		setLoading( true );
		setError( null );
		const params = new URLSearchParams( {
			page: String( page ),
			per_page: '20',
			days: '28',
		} );
		Object.entries( filters ).forEach( ( [ key, value ] ) => {
			if ( value ) {
				params.set( key, value );
			}
		} );
		apiFetch( { path: `citeoryx/v1/planning/opportunities?${ params }` } )
			.then( ( response ) => setData( response.data ) )
			.catch( ( err ) =>
				setError(
					getApiErrorMessage(
						err,
						__( '无法加载主题机会。', 'citeoryx' )
					)
				)
			)
			.finally( () => setLoading( false ) );
	}, [ page, filters ] );

	useEffect( () => {
		fetchOpportunities();
	}, [ fetchOpportunities ] );

	const updateFilter = ( key, value ) => {
		setPage( 1 );
		setFilters( ( current ) => ( { ...current, [ key ]: value } ) );
	};

	if ( loading && ! data ) {
		return <Spinner />;
	}

	return (
		<div className="citeoryx-planning">
			<PlanningCalendar />
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<div className="citeoryx-filters">
				<SelectControl
					label={ __( '机会类型', 'citeoryx' ) }
					value={ filters.type }
					options={ selectOptions(
						typeLabels,
						__( '全部类型', 'citeoryx' )
					) }
					onChange={ ( value ) => updateFilter( 'type', value ) }
				/>
				<SelectControl
					label={ __( '数据源', 'citeoryx' ) }
					value={ filters.source }
					options={ selectOptions(
						sourceLabels,
						__( '全部来源', 'citeoryx' )
					) }
					onChange={ ( value ) => updateFilter( 'source', value ) }
				/>
				<Button onClick={ fetchOpportunities } disabled={ loading }>
					{ loading
						? __( '刷新中…', 'citeoryx' )
						: __( '刷新', 'citeoryx' ) }
				</Button>
			</div>
			{ data?.summary?.data_limited && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'候选数据已达到 1000 行上限，请按数据源筛选后复核。',
						'citeoryx'
					) }
				</Notice>
			) }
			{ data?.items?.length ? (
				<div className="citeoryx-planning__list">
					{ data.items.map( ( item ) => (
						<Card key={ item.id }>
							<CardHeader>
								<strong>{ item.query }</strong>
								<span className="citeoryx-priority">
									{ item.priority_score }
								</span>
							</CardHeader>
							<CardBody>
								<p className="citeoryx-planning__meta">
									{ typeLabels[ item.type ] } ·{ ' ' }
									{ sourceLabels[ item.source ] ||
										item.source }
								</p>
								<p>
									{ __( '展现', 'citeoryx' ) }{ ' ' }
									{ item.metrics.impressions } ·{ ' ' }
									{ __( '点击', 'citeoryx' ) }{ ' ' }
									{ item.metrics.clicks } ·{ ' ' }
									{ __( '平均排名', 'citeoryx' ) }{ ' ' }
									{ item.metrics.position_avg ?? '—' }
								</p>
								<p>
									<strong>
										{
											actionLabels[
												item.recommended_action
											]
										}
									</strong>
								</p>
								<ul className="citeoryx-issue-list">
									{ item.pages.map( ( candidate ) => (
										<li key={ candidate.content_id }>
											<a
												href={ candidate.url }
												target="_blank"
												rel="noreferrer"
											>
												{ candidate.url }
											</a>
											{ ` · ${ candidate.status } · #${
												candidate.position_avg ?? '—'
											}` }
										</li>
									) ) }
								</ul>
							</CardBody>
						</Card>
					) ) }
				</div>
			) : (
				! loading && (
					<Notice status="info" isDismissible={ false }>
						{ __( '当前筛选下没有可确认的主题机会。', 'citeoryx' ) }
					</Notice>
				)
			) }
			{ data?.pagination && data.pagination.total_pages > 1 && (
				<div className="citeoryx-pagination">
					<Button
						disabled={ page <= 1 || loading }
						onClick={ () => setPage( page - 1 ) }
					>
						{ __( '上一页', 'citeoryx' ) }
					</Button>
					<span>{ `${ data.pagination.page } / ${ data.pagination.total_pages }` }</span>
					<Button
						disabled={
							page >= data.pagination.total_pages || loading
						}
						onClick={ () => setPage( page + 1 ) }
					>
						{ __( '下一页', 'citeoryx' ) }
					</Button>
				</div>
			) }
		</div>
	);
};

export default Planning;
