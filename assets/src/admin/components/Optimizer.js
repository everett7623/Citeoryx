import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
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
	const [ contentTotal, setContentTotal ] = useState( 0 );
	const [ contentPage, setContentPage ] = useState( 1 );
	const [ itemLoading, setItemLoading ] = useState( true );
	const contentRequestIdRef = useRef( 0 );
	const analysisRequestIdRef = useRef( 0 );
	const workspaceClass = canUseAi
		? 'citeoryx-optimizer__workspace'
		: 'citeoryx-optimizer__workspace citeoryx-optimizer__workspace--rules-only';

	const fetchContent = useCallback( ( currentPage ) => {
		const requestId = ++contentRequestIdRef.current;
		setItemLoading( true );
		apiFetch( {
			path: `citeoryx/v1/content?page=${ currentPage }&per_page=20`,
		} )
			.then( ( response ) => {
				if ( requestId !== contentRequestIdRef.current ) {
					return;
				}
				const list = response.data.items || [];
				setItems(
					list.map( ( item ) => ( {
						label: item.canonical_url,
						value: item.id.toString(),
					} ) )
				);
				setContentTotal( response.data.total || 0 );
			} )
			.catch( ( requestError ) => {
				if ( requestId !== contentRequestIdRef.current ) {
					return;
				}
				setItems( [] );
				setError(
					getApiErrorMessage(
						requestError,
						__( '无法加载内容列表。', 'citeoryx' )
					)
				);
			} )
			.finally( () => {
				if ( requestId === contentRequestIdRef.current ) {
					setItemLoading( false );
				}
			} );
	}, [] );

	useEffect( () => {
		fetchContent( contentPage );
	}, [ contentPage, fetchContent ] );

	const selectContent = ( value ) => {
		++analysisRequestIdRef.current;
		setContentId( value );
		setData( null );
		setError( null );
		setLoading( false );
	};

	const contentTotalPages = Math.ceil( contentTotal / 20 );
	const changeContentPage = ( nextPage ) => {
		selectContent( '' );
		setContentPage( nextPage );
	};

	const analyze = () => {
		if ( ! contentId ) {
			setError( __( '请选择要分析的内容。', 'citeoryx' ) );
			return;
		}
		const requestId = ++analysisRequestIdRef.current;
		setLoading( true );
		setError( null );
		apiFetch( { path: `citeoryx/v1/optimizer/${ contentId }` } )
			.then( ( response ) => {
				if ( requestId === analysisRequestIdRef.current ) {
					setData( response.data );
				}
			} )
			.catch( ( err ) => {
				if ( requestId === analysisRequestIdRef.current ) {
					setError(
						getApiErrorMessage(
							err,
							__( '分析失败。', 'citeoryx' )
						)
					);
				}
			} )
			.finally( () => {
				if ( requestId === analysisRequestIdRef.current ) {
					setLoading( false );
				}
			} );
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
				{ contentTotalPages > 1 && (
					<div className="citeoryx-pagination">
						<Button
							disabled={ contentPage <= 1 || itemLoading }
							onClick={ () =>
								changeContentPage( contentPage - 1 )
							}
						>
							{ __( '上一页', 'citeoryx' ) }
						</Button>
						<span>{ `${ contentPage } / ${ contentTotalPages }` }</span>
						<Button
							disabled={
								contentPage >= contentTotalPages || itemLoading
							}
							onClick={ () =>
								changeContentPage( contentPage + 1 )
							}
						>
							{ __( '下一页', 'citeoryx' ) }
						</Button>
					</div>
				) }
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
