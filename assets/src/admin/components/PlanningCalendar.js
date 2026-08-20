import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner } from '@wordpress/components';
import { getApiErrorMessage } from '../apiError';
import PlanningCalendarLists from './PlanningCalendarLists';

const PlanningCalendar = () => {
	const canManageIssues = Boolean(
		window.citeoryxAdmin?.user?.canManageIssues
	);
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ updating, setUpdating ] = useState( null );
	const [ error, setError ] = useState( null );

	const fetchCalendar = useCallback( () => {
		setLoading( true );
		setError( null );
		apiFetch( {
			path: 'citeoryx/v1/planning/calendar?horizon_days=90&limit=50',
		} )
			.then( ( response ) => setData( response.data ) )
			.catch( ( err ) =>
				setError(
					getApiErrorMessage(
						err,
						__( '无法加载发布与复核计划。', 'citeoryx' )
					)
				)
			)
			.finally( () => setLoading( false ) );
	}, [] );

	useEffect( () => {
		fetchCalendar();
	}, [ fetchCalendar ] );

	const completeReview = ( contentId ) => {
		setUpdating( contentId );
		setError( null );
		apiFetch( {
			path: `citeoryx/v1/planning/reviews/${ contentId }/complete`,
			method: 'POST',
		} )
			.then( fetchCalendar )
			.catch( ( err ) =>
				setError(
					getApiErrorMessage(
						err,
						__( '无法标记复核完成。', 'citeoryx' )
					)
				)
			)
			.finally( () => setUpdating( null ) );
	};

	if ( loading && ! data ) {
		return <Spinner />;
	}

	return (
		<div className="citeoryx-calendar">
			<div className="citeoryx-dashboard__actions">
				<Button
					onClick={ fetchCalendar }
					disabled={ loading || updating !== null }
				>
					{ loading
						? __( '刷新中…', 'citeoryx' )
						: __( '刷新发布与复核计划', 'citeoryx' ) }
				</Button>
				{ data && (
					<span className="citeoryx-planning__meta">
						{ sprintf(
							/* translators: 1: timezone, 2: review cycle days. */
							__(
								'站点时区：%1$s · 默认复核周期：%2$d 天',
								'citeoryx'
							),
							data.timezone,
							data.review_cycle_days
						) }
					</span>
				) }
			</div>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ ( data?.scheduled?.data_limited ||
				data?.overdue_reviews?.data_limited ) && (
				<Notice status="warning" isDismissible={ false }>
					{ __( '列表已达到 50 条显示上限。', 'citeoryx' ) }
				</Notice>
			) }
			{ data && (
				<PlanningCalendarLists
					data={ data }
					canManageIssues={ canManageIssues }
					updating={ updating }
					onComplete={ completeReview }
				/>
			) }
		</div>
	);
};

export default PlanningCalendar;
