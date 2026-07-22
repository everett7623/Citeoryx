import {
	buildRevisionPayload,
	createRevisionDraft,
	getRevisionChanges,
} from './optimizerRevision';

const editor = {
	title: 'Original title',
	content: '<p>Original content</p>',
	excerpt: 'Original excerpt',
	base_content_hash: 'a'.repeat( 64 ),
};

describe( 'optimizer revision helpers', () => {
	test( 'creates an editable draft without mutating the snapshot', () => {
		const draft = createRevisionDraft( editor );
		draft.title = 'Changed title';

		expect( editor.title ).toBe( 'Original title' );
		expect( draft ).toEqual( {
			title: 'Changed title',
			content: '<p>Original content</p>',
			excerpt: 'Original excerpt',
			summary: '',
		} );
	} );

	test( 'returns only changed editable fields', () => {
		const draft = createRevisionDraft( editor );
		draft.content = '<p>Updated content</p>';

		expect( getRevisionChanges( editor, draft ) ).toEqual( [
			{
				field: 'content',
				before: '<p>Original content</p>',
				after: '<p>Updated content</p>',
			},
		] );
	} );

	test( 'builds the complete REST request contract', () => {
		const draft = {
			...createRevisionDraft( editor ),
			title: 'Updated title',
			summary: 'Refresh examples',
		};

		expect( buildRevisionPayload( '9', editor, draft ) ).toEqual( {
			content_id: 9,
			title: 'Updated title',
			content: '<p>Original content</p>',
			excerpt: 'Original excerpt',
			base_content_hash: 'a'.repeat( 64 ),
			summary: 'Refresh examples',
		} );
	} );
} );
