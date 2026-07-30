import {
	getAiAnalysisPath,
	getAiAnalysisResult,
	isAiTaskActive,
} from './aiAnalysis';

describe( 'AI analysis task helpers', () => {
	test( 'builds trigger and status paths without changing the content ID', () => {
		expect( getAiAnalysisPath( 42 ) ).toBe(
			'citeoryx/v1/integrations/ai/analyze/42'
		);
		expect( getAiAnalysisPath( 42, 'task id' ) ).toBe(
			'citeoryx/v1/integrations/ai/analyze/42?task_id=task%20id'
		);
	} );

	test( 'only queued and running tasks are active', () => {
		expect( isAiTaskActive( { status: 'queued' } ) ).toBe( true );
		expect( isAiTaskActive( { status: 'running' } ) ).toBe( true );
		expect( isAiTaskActive( { status: 'completed' } ) ).toBe( false );
		expect( isAiTaskActive( { status: 'failed' } ) ).toBe( false );
		expect( isAiTaskActive( { status: 'idle' } ) ).toBe( false );
	} );

	test( 'normalizes missing and completed result fields', () => {
		expect( getAiAnalysisResult( null ) ).toEqual( {
			discoverability: {},
			suggestions: [],
		} );

		const result = getAiAnalysisResult( {
			result: {
				discoverability: { score: 81 },
				suggestions: { suggestions: [ { title: 'Improve evidence' } ] },
			},
		} );
		expect( result.discoverability.score ).toBe( 81 );
		expect( result.suggestions ).toHaveLength( 1 );
	} );
} );
