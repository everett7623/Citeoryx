import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { getApiErrorMessage } from '../apiError';
import AiAnalysisPanel from './AiAnalysisPanel';
import OptimizerResults from './OptimizerResults';
import OptimizerRevisionPanel from './OptimizerRevisionPanel';
import {
	Card,
	CardBody,
	Button,
	SelectControl,
	Spinner,
	Notice,
} from '@wordpress/components';

const Optimizer = () => {
	const canUseAi = Boolean( window.citeoryxAdmin?.user?.canUseAi );
	const canManageIntegrations = Boolean(
		window.citeoryxAdmin?.user?.canManageIntegrations
	);
	const canApplyChanges = Boolean(
		window.citeoryxAdmin?.user?.canApplyChanges
	);
	const canScan = Boolean( window.citeoryxAdmin?.user?.canScan );
	const [ contentId, setContentId ] = useState( '' );
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ items, setItems ] = useState( [] );
	const [ itemLoading, setItemLoading ] = useState( true );
	const workspaceClass = canUseAi
		? 'citeoryx-optimizer__workspace'
		: 'citeoryx-optimizer__workspace citeoryx-optimizer__workspace--rules-only';

	useEffect( () => {
		apiFetch( { path: 'citeoryx/v1/content?per_page=100' } )
			.then( ( response ) => {
				const list = response.data.items || [];
				setItems(
					list.map( ( item ) => ( {
						label: item.canonical_url,
						value: item.id.toString(),
					} ) )
				);
			} )
			.catch( ( requestError ) => {
				setItems( [] );
				setError(
					getApiErrorMessage(
						requestError,
						__( '无法加载内容列表。', 'citeoryx' )
					)
				);
			} )
			.finally( () => setItemLoading( false ) );
	}, [] );

	const selectContent = ( value ) => {
		setContentId( value );
		setData( null );
		setError( null );
	};

	const analyze = () => {
		if ( ! contentId ) {
			setError( __( '请选择要分析的内容。', 'citeoryx' ) );
			return;
		}
		setLoading( true );
		setError( null );
		apiFetch( { path: `citeoryx/v1/optimizer/${ contentId }` } )
			.then( ( response ) => setData( response.data ) )
			.catch( ( err ) =>
				setError(
					getApiErrorMessage( err, __( '分析失败。', 'citeoryx' ) )
				)
			)
			.finally( () => setLoading( false ) );
	};

	const renderSelector = () => {
		if ( itemLoading ) {
			return <Spinner />;
		}
		if ( items.length === 0 ) {
			return (
				<Notice status="info" isDismissible={ false }>
					{ __( '尚无可分析内容，请先运行内容扫描。', 'citeoryx' ) }
				</Notice>
			);
		}

		return (
			<div className="citeoryx-optimizer__selector">
				<SelectControl
					label={ __( '选择内容', 'citeoryx' ) }
					value={ contentId }
					options={ [
						{
							label: __( '请选择', 'citeoryx' ),
							value: '',
						},
						...items,
					] }
					onChange={ selectContent }
				/>
				<Button
					variant="primary"
					onClick={ analyze }
					disabled={ loading || ! contentId }
				>
					{ loading
						? __( '分析中…', 'citeoryx' )
						: __( '生成优化建议', 'citeoryx' ) }
				</Button>
			</div>
		);
	};

	return (
		<div className="citeoryx-optimizer">
			<h2>{ __( '内容优化器', 'citeoryx' ) }</h2>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			<Card>
				<CardBody>{ renderSelector() }</CardBody>
			</Card>

			{ data && (
				<>
					<div className={ workspaceClass }>
						<OptimizerResults data={ data } />
						{ canUseAi && (
							<AiAnalysisPanel
								key={ data.content.id }
								canManageIntegrations={ canManageIntegrations }
								contentId={ data.content.id }
							/>
						) }
					</div>

					{ canApplyChanges && data.editor?.available && (
						<OptimizerRevisionPanel
							key={ `${ data.content.id }-${ data.editor.base_content_hash }` }
							canScan={ canScan }
							contentId={ data.content.id }
							editor={ data.editor }
							onDataRefresh={ setData }
							performance={ data.revision_performance }
						/>
					) }
					{ canApplyChanges &&
						data.editor &&
						! data.editor.available && (
							<Notice status="warning" isDismissible={ false }>
								{ data.editor.message }
							</Notice>
						) }
				</>
			) }
		</div>
	);
};

export default Optimizer;
