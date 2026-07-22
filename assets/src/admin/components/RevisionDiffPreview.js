import { __ } from '@wordpress/i18n';
import { Notice } from '@wordpress/components';

const labels = {
	title: __( '标题', 'citeoryx' ),
	excerpt: __( '摘要', 'citeoryx' ),
	content: __( '正文', 'citeoryx' ),
};

const RevisionDiffPreview = ( { changes } ) => {
	if ( changes.length === 0 ) {
		return (
			<Notice status="info" isDismissible={ false }>
				{ __( '尚未修改标题、摘要或正文。', 'citeoryx' ) }
			</Notice>
		);
	}

	return (
		<div className="citeoryx-revision-diff">
			{ changes.map( ( change ) => (
				<section key={ change.field }>
					<h4>{ labels[ change.field ] }</h4>
					<div className="citeoryx-revision-diff__columns">
						<div>
							<strong>{ __( '当前版本', 'citeoryx' ) }</strong>
							<pre>
								{ change.before || __( '（空）', 'citeoryx' ) }
							</pre>
						</div>
						<div>
							<strong>{ __( '拟议版本', 'citeoryx' ) }</strong>
							<pre>
								{ change.after || __( '（空）', 'citeoryx' ) }
							</pre>
						</div>
					</div>
				</section>
			) ) }
		</div>
	);
};

export default RevisionDiffPreview;
