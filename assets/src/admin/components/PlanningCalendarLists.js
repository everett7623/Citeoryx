import { __, sprintf } from '@wordpress/i18n';
import { Button, Card, CardBody, CardHeader } from '@wordpress/components';

const formatSiteDate = ( value ) =>
	value
		? value.replace( 'T', ' ' ).replace( /([+-]\d{2}:\d{2})$/, ' $1' )
		: '—';

const ScheduledList = ( { items } ) => (
	<Card>
		<CardHeader>{ __( '未来 90 天发布计划', 'citeoryx' ) }</CardHeader>
		<CardBody>
			{ items.length ? (
				<ul className="citeoryx-calendar__list">
					{ items.map( ( item ) => (
						<li className="citeoryx-calendar__item" key={ item.id }>
							<a href={ item.edit_url }>
								{ item.title || __( '无标题', 'citeoryx' ) }
							</a>
							<span>{ formatSiteDate( item.publish_at ) }</span>
						</li>
					) ) }
				</ul>
			) : (
				<p>{ __( '未来 90 天暂无定时发布内容。', 'citeoryx' ) }</p>
			) }
		</CardBody>
	</Card>
);

const ReviewList = ( { items, canManageIssues, updating, onComplete } ) => (
	<Card>
		<CardHeader>{ __( '已到期复核', 'citeoryx' ) }</CardHeader>
		<CardBody>
			{ items.length ? (
				<ul className="citeoryx-calendar__list">
					{ items.map( ( item ) => (
						<li
							className="citeoryx-calendar__item"
							key={ item.content_id }
						>
							<div>
								<a href={ item.edit_url || item.url }>
									{ item.title }
								</a>
								<span>
									{ sprintf(
										/* translators: %d: overdue days. */
										__( '已逾期 %d 天', 'citeoryx' ),
										item.overdue_days
									) }
								</span>
							</div>
							{ canManageIssues && (
								<Button
									variant="secondary"
									disabled={ updating === item.content_id }
									onClick={ () =>
										onComplete( item.content_id )
									}
								>
									{ updating === item.content_id
										? __( '保存中…', 'citeoryx' )
										: __( '标记已复核', 'citeoryx' ) }
								</Button>
							) }
						</li>
					) ) }
				</ul>
			) : (
				<p>{ __( '当前没有到期复核内容。', 'citeoryx' ) }</p>
			) }
		</CardBody>
	</Card>
);

const PlanningCalendarLists = ( {
	data,
	canManageIssues,
	updating,
	onComplete,
} ) => (
	<div className="citeoryx-calendar__grid">
		<ScheduledList items={ data.scheduled.items } />
		<ReviewList
			items={ data.overdue_reviews.items }
			canManageIssues={ canManageIssues }
			updating={ updating }
			onComplete={ onComplete }
		/>
	</div>
);

export default PlanningCalendarLists;
