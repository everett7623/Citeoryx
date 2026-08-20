import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	Spinner,
} from '@wordpress/components';
import { getApiErrorMessage } from '../apiError';
import { getAiAnalysisPath, isAiTaskActive } from '../aiAnalysis';
import AiAnalysisResult from './AiAnalysisResult';

const AiAnalysisPanel = ( { canManageIntegrations, contentId } ) => {
	const [ availability, setAvailability ] = useState( null );
	const [ task, setTask ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ submitting, setSubmitting ] = useState( false );
	const [ error, setError ] = useState( '' );
	const taskId = task?.task_id;
	const taskStatus = task?.status;
	const active = isAiTaskActive( task );

	useEffect( () => {
		let cancelled = false;
		setLoading( true );
		setError( '' );
		setTask( null );

		Promise.all( [
			apiFetch( { path: 'citeoryx/v1/integrations/ai/availability' } ),
			apiFetch( { path: getAiAnalysisPath( contentId ) } ),
		] )
			.then( ( [ availabilityResponse, taskResponse ] ) => {
				if ( cancelled ) {
					return;
				}
				setAvailability( availabilityResponse.data );
				setTask( taskResponse.data );
			} )
			.catch( ( requestError ) => {
				if ( ! cancelled ) {
					setError(
						getApiErrorMessage(
							requestError,
							__( '无法加载 AI 分析状态。', 'citeoryx' )
						)
					);
				}
			} )
			.finally( () => {
				if ( ! cancelled ) {
					setLoading( false );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ contentId ] );

	useEffect( () => {
		if ( ! active ) {
			return undefined;
		}

		let cancelled = false;
		let timer;
		const poll = () => {
			apiFetch( {
				path: getAiAnalysisPath( contentId, taskId ),
			} )
				.then( ( response ) => {
					if ( ! cancelled ) {
						setTask( response.data );
						setError( '' );
					}
				} )
				.catch( ( requestError ) => {
					if ( ! cancelled ) {
						setError(
							getApiErrorMessage(
								requestError,
								__( '无法刷新 AI 分析状态。', 'citeoryx' )
							)
						);
					}
				} )
				.finally( () => {
					if ( ! cancelled ) {
						timer = window.setTimeout( poll, 2500 );
					}
				} );
		};
		timer = window.setTimeout( poll, 2500 );

		return () => {
			cancelled = true;
			window.clearTimeout( timer );
		};
	}, [ active, contentId, taskId, taskStatus ] );

	const trigger = () => {
		setSubmitting( true );
		setError( '' );
		apiFetch( {
			path: getAiAnalysisPath( contentId ),
			method: 'POST',
		} )
			.then( ( response ) => setTask( response.data ) )
			.catch( ( requestError ) =>
				setError(
					getApiErrorMessage(
						requestError,
						__( '无法启动 AI 深度分析。', 'citeoryx' )
					)
				)
			)
			.finally( () => setSubmitting( false ) );
	};

	const actionLabel = () => {
		if ( active ) {
			return __( '后台分析中…', 'citeoryx' );
		}
		if ( taskStatus === 'completed' ) {
			return __( '重新分析', 'citeoryx' );
		}
		return __( '开始 AI 深度分析', 'citeoryx' );
	};

	return (
		<Card className="citeoryx-ai-analysis">
			<CardHeader>{ __( 'AI 深度分析', 'citeoryx' ) }</CardHeader>
			<CardBody>
				{ loading && <Spinner /> }
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }
				{ ! loading && availability?.enabled === false && (
					<Notice status="warning" isDismissible={ false }>
						{ __( 'AI 分析已关闭。', 'citeoryx' ) }
						{ canManageIntegrations && (
							<>
								{ ' ' }
								<a href="admin.php?page=citeoryx-dashboard#/integrations">
									{ __( '前往集成设置启用', 'citeoryx' ) }
								</a>
							</>
						) }
					</Notice>
				) }
				{ ! loading &&
					availability?.enabled !== false &&
					availability &&
					! availability.configured && (
						<Notice status="warning" isDismissible={ false }>
							{ __( '尚未配置可用的 AI 提供商。', 'citeoryx' ) }
							{ canManageIntegrations && (
								<>
									{ ' ' }
									<a href="admin.php?page=citeoryx-dashboard#/integrations">
										{ __( '前往集成设置', 'citeoryx' ) }
									</a>
								</>
							) }
						</Notice>
					) }
				{ active && (
					<Notice status="info" isDismissible={ false }>
						{ __(
							'AI 正在后台分析。可以离开此页面，稍后返回查看结果。',
							'citeoryx'
						) }
					</Notice>
				) }
				{ task?.status === 'failed' && (
					<Notice status="error" isDismissible={ false }>
						{ task.error ||
							__( 'AI 分析失败，请重试。', 'citeoryx' ) }
					</Notice>
				) }
				{ task?.status === 'completed' && (
					<AiAnalysisResult task={ task } />
				) }
				{ ! loading &&
					availability?.enabled !== false &&
					availability?.configured && (
						<Button
							variant="primary"
							onClick={ trigger }
							isBusy={ submitting || active }
							disabled={ submitting || active }
						>
							{ actionLabel() }
						</Button>
					) }
			</CardBody>
		</Card>
	);
};

export default AiAnalysisPanel;
