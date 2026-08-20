import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { getApiErrorMessage } from '../apiError';
import {
	buildRevisionPayload,
	createRevisionDraft,
	getRevisionChanges,
} from '../optimizerRevision';
import RevisionDiffPreview from './RevisionDiffPreview';

const workflowMessage = ( state ) => {
	const messages = {
		awaiting_review: __(
			'修订正在等待 WordPress 审核，父内容尚未改变。',
			'citeoryx'
		),
		applied_unpublished: __(
			'提案已应用，但内容尚未公开发布。发布后即可执行验证扫描。',
			'citeoryx'
		),
		published_pending_scan: __(
			'提案已应用到公开内容，等待重新扫描验证。',
			'citeoryx'
		),
		verified: __( '提案已发布，并通过最新内容扫描验证。', 'citeoryx' ),
		superseded: __(
			'当前内容与基础快照和提案均不一致，请人工检查后重新生成建议。',
			'citeoryx'
		),
	};

	return messages[ state ] || '';
};

const workflowNoticeStatus = ( state ) => {
	if ( state === 'verified' ) {
		return 'success';
	}
	if ( state === 'superseded' || state === 'published_pending_scan' ) {
		return 'warning';
	}
	return 'info';
};

const sourceLabel = ( source ) => {
	const labels = {
		google_search_console: 'Google Search Console',
		bing_webmaster_tools: 'Bing Webmaster Tools',
	};

	return labels[ source ] || source;
};

const formatMetric = ( value, type ) => {
	if ( value === null || value === undefined ) {
		return __( '暂无数据', 'citeoryx' );
	}
	if ( type === 'percent' ) {
		return `${ ( value * 100 ).toFixed( 2 ) }%`;
	}
	if ( type === 'position' ) {
		return Number( value ).toFixed( 2 );
	}
	return Number( value ).toLocaleString();
};

const formatDelta = ( value, type ) => {
	if ( value === null || value === undefined ) {
		return '–';
	}
	const prefix = value > 0 ? '+' : '';
	return `${ prefix }${ formatMetric( value, type ) }`;
};

const performanceStateMessage = ( state, elapsedDays, days ) => {
	if ( state === 'ready' ) {
		return __( '已收集完整对比周期。', 'citeoryx' );
	}
	if ( state === 'unavailable' ) {
		return __( '对应日期范围内尚无已导入的搜索表现数据。', 'citeoryx' );
	}
	return `${ __(
		'数据收集中：已过',
		'citeoryx'
	) } ${ elapsedDays }/${ days } ${ __(
		'天；搜索平台数据可能延迟。',
		'citeoryx'
	) }`;
};

const RevisionPerformanceStatus = ( { performance } ) => {
	if ( ! performance?.available ) {
		return null;
	}

	const metrics = [
		{ key: 'clicks', label: __( '点击', 'citeoryx' ), type: 'number' },
		{
			key: 'impressions',
			label: __( '展示', 'citeoryx' ),
			type: 'number',
		},
		{ key: 'ctr', label: 'CTR', type: 'percent' },
		{
			key: 'position_avg',
			label: __( '平均排名', 'citeoryx' ),
			type: 'position',
		},
	];

	return (
		<section className="citeoryx-revision-performance">
			<h3>{ __( '发布后效果', 'citeoryx' ) }</h3>
			{ performance.windows.map( ( window ) => (
				<section
					className="citeoryx-revision-performance__window"
					key={ window.days }
				>
					<h4>{ `${ window.days } ${ __(
						'天对比',
						'citeoryx'
					) }` }</h4>
					<p className="citeoryx-muted">
						{ performanceStateMessage(
							window.state,
							window.elapsed_days,
							window.days
						) }
					</p>
					<p className="citeoryx-muted">
						{ `${ __( '基线', 'citeoryx' ) } ${
							window.baseline.start_date
						} - ${ window.baseline.end_date } | ${ __(
							'当前',
							'citeoryx'
						) } ${ window.current.start_date } - ${
							window.current.end_date
						}` }
					</p>
					{ window.sources.map( ( source ) => (
						<div
							className="citeoryx-revision-performance__source"
							key={ source.source }
						>
							<h5>{ sourceLabel( source.source ) }</h5>
							<table>
								<thead>
									<tr>
										<th>{ __( '指标', 'citeoryx' ) }</th>
										<th>{ __( '基线', 'citeoryx' ) }</th>
										<th>{ __( '当前', 'citeoryx' ) }</th>
										<th>{ __( '变化', 'citeoryx' ) }</th>
									</tr>
								</thead>
								<tbody>
									{ metrics.map( ( metric ) => (
										<tr key={ metric.key }>
											<td>{ metric.label }</td>
											<td>
												{ formatMetric(
													source.baseline[
														metric.key
													],
													metric.type
												) }
											</td>
											<td>
												{ formatMetric(
													source.current[
														metric.key
													],
													metric.type
												) }
											</td>
											<td>
												{ formatDelta(
													source.delta[ metric.key ],
													metric.type
												) }
											</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
					) ) }
				</section>
			) ) }
		</section>
	);
};

const RevisionWorkflowStatus = ( {
	canScan,
	onRefresh,
	onVerify,
	refreshing,
	submitting,
	verifying,
	workflow,
} ) => {
	if ( ! workflow || workflow.state === 'idle' ) {
		return null;
	}

	return (
		<div className="citeoryx-revision-workflow">
			<Notice
				status={ workflowNoticeStatus( workflow.state ) }
				isDismissible={ false }
			>
				<strong>{ __( '优化闭环', 'citeoryx' ) }</strong>
				{ `：${ workflowMessage( workflow.state ) }` }
				{ workflow.revision?.compare_url && (
					<>
						{ ' ' }
						<a href={ workflow.revision.compare_url }>
							{ __( '查看修订', 'citeoryx' ) }
						</a>
					</>
				) }
			</Notice>
			<div className="citeoryx-revision-workflow__actions">
				<Button
					variant="secondary"
					onClick={ onRefresh }
					isBusy={ refreshing }
					disabled={ refreshing || submitting || verifying }
				>
					{ refreshing
						? __( '刷新中…', 'citeoryx' )
						: __( '刷新状态', 'citeoryx' ) }
				</Button>
				{ workflow.can_verify && canScan && (
					<Button
						variant="secondary"
						onClick={ onVerify }
						isBusy={ verifying }
						disabled={ refreshing || submitting || verifying }
					>
						{ verifying
							? __( '验证中…', 'citeoryx' )
							: __( '重新扫描并验证', 'citeoryx' ) }
					</Button>
				) }
			</div>
			{ workflow.can_verify && ! canScan && (
				<p className="citeoryx-muted">
					{ __( '需要内容扫描权限才能完成发布后验证。', 'citeoryx' ) }
				</p>
			) }
		</div>
	);
};

const OptimizerRevisionPanel = ( {
	canScan,
	contentId,
	editor,
	onDataRefresh,
	performance: initialPerformance,
} ) => {
	const [ draft, setDraft ] = useState( () => createRevisionDraft( editor ) );
	const [ submitting, setSubmitting ] = useState( false );
	const [ verifying, setVerifying ] = useState( false );
	const [ refreshing, setRefreshing ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ result, setResult ] = useState( null );
	const [ workflow, setWorkflow ] = useState( editor.workflow || null );
	const [ performance, setPerformance ] = useState(
		initialPerformance || null
	);
	const [ submittedDraft, setSubmittedDraft ] = useState( '' );
	const activeRef = useRef( true );
	const operationRef = useRef( null );
	const changes = getRevisionChanges( editor, draft );
	const draftSignature = JSON.stringify( draft );
	const resultMessage = result?.created
		? __( '修订已创建，可在 WordPress 中比较并审核。', 'citeoryx' )
		: __( '相同提案已存在，未重复创建修订。', 'citeoryx' );

	useEffect( () => {
		activeRef.current = true;

		return () => {
			activeRef.current = false;
		};
	}, [] );

	const update = ( field, value ) => {
		setDraft( ( current ) => ( { ...current, [ field ]: value } ) );
		setError( '' );
	};

	const submit = () => {
		if ( operationRef.current ) {
			return;
		}
		operationRef.current = 'submit';
		setSubmitting( true );
		setError( '' );
		apiFetch( {
			path: 'citeoryx/v1/recommendations/apply',
			method: 'POST',
			data: buildRevisionPayload( contentId, editor, draft ),
		} )
			.then( ( response ) => {
				if ( ! activeRef.current ) {
					return;
				}
				setResult( response.data.revision );
				setWorkflow( response.data.workflow );
				setSubmittedDraft( draftSignature );
			} )
			.catch( ( requestError ) => {
				if ( activeRef.current ) {
					setError(
						getApiErrorMessage(
							requestError,
							__( '无法创建修订，请稍后重试。', 'citeoryx' )
						)
					);
				}
			} )
			.finally( () => {
				if ( operationRef.current === 'submit' ) {
					operationRef.current = null;
				}
				if ( activeRef.current ) {
					setSubmitting( false );
				}
			} );
	};

	const fetchWorkflow = () => {
		if ( ! activeRef.current ) {
			return Promise.resolve( null );
		}

		return apiFetch( {
			path: `citeoryx/v1/optimizer/${ contentId }`,
		} ).then( ( response ) => {
			if ( ! activeRef.current ) {
				return response;
			}
			setWorkflow( response.data.editor?.workflow || null );
			setPerformance( response.data.revision_performance || null );
			onDataRefresh( response.data );
			return response;
		} );
	};

	const refresh = () => {
		if ( operationRef.current ) {
			return;
		}
		operationRef.current = 'refresh';
		setRefreshing( true );
		setError( '' );
		fetchWorkflow()
			.catch( ( requestError ) => {
				if ( activeRef.current ) {
					setError(
						getApiErrorMessage(
							requestError,
							__( '无法刷新闭环状态，请稍后重试。', 'citeoryx' )
						)
					);
				}
			} )
			.finally( () => {
				if ( operationRef.current === 'refresh' ) {
					operationRef.current = null;
				}
				if ( activeRef.current ) {
					setRefreshing( false );
				}
			} );
	};

	const verify = () => {
		if ( operationRef.current ) {
			return;
		}
		operationRef.current = 'verify';
		setVerifying( true );
		setError( '' );
		apiFetch( {
			path: `citeoryx/v1/content/${ contentId }/scan`,
			method: 'POST',
		} )
			.then( fetchWorkflow )
			.catch( ( requestError ) => {
				if ( activeRef.current ) {
					setError(
						getApiErrorMessage(
							requestError,
							__( '无法完成发布后验证，请稍后重试。', 'citeoryx' )
						)
					);
				}
			} )
			.finally( () => {
				if ( operationRef.current === 'verify' ) {
					operationRef.current = null;
				}
				if ( activeRef.current ) {
					setVerifying( false );
				}
			} );
	};

	return (
		<Card>
			<CardHeader>{ __( '创建安全修订', 'citeoryx' ) }</CardHeader>
			<CardBody>
				<RevisionWorkflowStatus
					canScan={ canScan }
					onRefresh={ refresh }
					onVerify={ verify }
					refreshing={ refreshing }
					submitting={ submitting }
					verifying={ verifying }
					workflow={ workflow }
				/>
				<RevisionPerformanceStatus performance={ performance } />
				<Notice status="info" isDismissible={ false }>
					{ __(
						'此操作只创建 WordPress Revision，不会修改或发布当前内容。',
						'citeoryx'
					) }
				</Notice>
				{ ! editor.revisions_enabled && (
					<Notice status="warning" isDismissible={ false }>
						{ editor.message }
					</Notice>
				) }
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }
				{ result && (
					<Notice status="success" isDismissible={ false }>
						{ `${ resultMessage } ` }
						<a href={ result.compare_url }>
							{ __( '查看修订', 'citeoryx' ) }
						</a>
					</Notice>
				) }
				<TextControl
					label={ __( '拟议标题', 'citeoryx' ) }
					value={ draft.title }
					onChange={ ( value ) => update( 'title', value ) }
				/>
				<TextareaControl
					label={ __( '拟议摘要', 'citeoryx' ) }
					value={ draft.excerpt }
					onChange={ ( value ) => update( 'excerpt', value ) }
					rows={ 4 }
				/>
				<TextareaControl
					label={ __( '拟议正文', 'citeoryx' ) }
					value={ draft.content }
					onChange={ ( value ) => update( 'content', value ) }
					rows={ 14 }
				/>
				<TextareaControl
					label={ __( '更新说明（可选）', 'citeoryx' ) }
					value={ draft.summary }
					onChange={ ( value ) => update( 'summary', value ) }
					rows={ 3 }
				/>
				<h3>{ __( '差异预览', 'citeoryx' ) }</h3>
				<RevisionDiffPreview changes={ changes } />
				<Button
					variant="primary"
					onClick={ submit }
					isBusy={ submitting }
					disabled={
						submitting ||
						refreshing ||
						verifying ||
						! editor.revisions_enabled ||
						changes.length === 0 ||
						submittedDraft === draftSignature
					}
				>
					{ submitting
						? __( '创建中…', 'citeoryx' )
						: __( '创建 Revision', 'citeoryx' ) }
				</Button>
			</CardBody>
		</Card>
	);
};

export default OptimizerRevisionPanel;
