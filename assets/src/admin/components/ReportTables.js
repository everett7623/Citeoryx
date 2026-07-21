import { __ } from '@wordpress/i18n';
import { Card, CardBody, CardHeader } from '@wordpress/components';

const labelFor = ( value ) => value || __( '未知', 'citeoryx' );

export const DistributionTable = ( { title, items, emptyLabel } ) => (
	<Card>
		<CardHeader>{ title }</CardHeader>
		<CardBody>
			{ items.length === 0 ? (
				<p>{ emptyLabel }</p>
			) : (
				<table className="citeoryx-report-table">
					<thead>
						<tr>
							<th>{ __( '项目', 'citeoryx' ) }</th>
							<th>{ __( '数量', 'citeoryx' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ items.map( ( item ) => (
							<tr key={ item.label }>
								<td>{ labelFor( item.label ) }</td>
								<td>{ item.count }</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</CardBody>
	</Card>
);

export const TopIssuesTable = ( { items } ) => (
	<Card>
		<CardHeader>{ __( '优先问题', 'citeoryx' ) }</CardHeader>
		<CardBody>
			{ items.length === 0 ? (
				<p>{ __( '暂无待处理问题。', 'citeoryx' ) }</p>
			) : (
				<table className="citeoryx-report-table">
					<thead>
						<tr>
							<th>{ __( '问题', 'citeoryx' ) }</th>
							<th>{ __( '优先级', 'citeoryx' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ items.map( ( issue ) => (
							<tr key={ issue.id }>
								<td>{ issue.title }</td>
								<td>{ issue.priority_score ?? '—' }</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</CardBody>
	</Card>
);

export const RecentScansTable = ( { items } ) => (
	<Card>
		<CardHeader>{ __( '最近扫描', 'citeoryx' ) }</CardHeader>
		<CardBody>
			{ items.length === 0 ? (
				<p>{ __( '尚未运行扫描。', 'citeoryx' ) }</p>
			) : (
				<table className="citeoryx-report-table">
					<thead>
						<tr>
							<th>{ __( '类型', 'citeoryx' ) }</th>
							<th>{ __( '状态', 'citeoryx' ) }</th>
							<th>{ __( '开始时间', 'citeoryx' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ items.map( ( scan ) => (
							<tr key={ scan.id }>
								<td>{ scan.scan_type }</td>
								<td>{ scan.status }</td>
								<td>{ scan.started_at || '—' }</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</CardBody>
	</Card>
);
