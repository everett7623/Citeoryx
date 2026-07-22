import { useState } from '@wordpress/element';
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

const OptimizerRevisionPanel = ( { contentId, editor } ) => {
	const [ draft, setDraft ] = useState( () => createRevisionDraft( editor ) );
	const [ submitting, setSubmitting ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ result, setResult ] = useState( null );
	const [ submittedDraft, setSubmittedDraft ] = useState( '' );
	const changes = getRevisionChanges( editor, draft );
	const draftSignature = JSON.stringify( draft );
	const resultMessage = result?.created
		? __( '修订已创建，可在 WordPress 中比较并审核。', 'citeoryx' )
		: __( '相同提案已存在，未重复创建修订。', 'citeoryx' );

	const update = ( field, value ) => {
		setDraft( ( current ) => ( { ...current, [ field ]: value } ) );
		setError( '' );
	};

	const submit = () => {
		setSubmitting( true );
		setError( '' );
		apiFetch( {
			path: 'citeoryx/v1/recommendations/apply',
			method: 'POST',
			data: buildRevisionPayload( contentId, editor, draft ),
		} )
			.then( ( response ) => {
				setResult( response.data.revision );
				setSubmittedDraft( draftSignature );
			} )
			.catch( ( requestError ) =>
				setError(
					getApiErrorMessage(
						requestError,
						__( '无法创建修订，请稍后重试。', 'citeoryx' )
					)
				)
			)
			.finally( () => setSubmitting( false ) );
	};

	return (
		<Card>
			<CardHeader>{ __( '创建安全修订', 'citeoryx' ) }</CardHeader>
			<CardBody>
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
