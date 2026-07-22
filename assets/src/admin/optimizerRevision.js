export const createRevisionDraft = ( editor ) => ( {
	title: editor?.title || '',
	content: editor?.content || '',
	excerpt: editor?.excerpt || '',
	summary: '',
} );

export const getRevisionChanges = ( editor, draft ) =>
	[ 'title', 'excerpt', 'content' ]
		.map( ( field ) => ( {
			field,
			before: String( editor?.[ field ] || '' ),
			after: String( draft?.[ field ] || '' ),
		} ) )
		.filter( ( change ) => change.before !== change.after );

export const buildRevisionPayload = ( contentId, editor, draft ) => ( {
	content_id: Number( contentId ),
	title: draft.title,
	content: draft.content,
	excerpt: draft.excerpt,
	base_content_hash: editor.base_content_hash,
	summary: draft.summary,
} );
