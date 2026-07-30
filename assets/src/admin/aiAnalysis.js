export const ACTIVE_AI_TASK_STATUSES = [ 'queued', 'running' ];

export const isAiTaskActive = ( task ) =>
	ACTIVE_AI_TASK_STATUSES.includes( task?.status );

export const getAiAnalysisPath = ( contentId, taskId = '' ) => {
	const path = `citeoryx/v1/integrations/ai/analyze/${ contentId }`;
	return taskId
		? `${ path }?task_id=${ encodeURIComponent( taskId ) }`
		: path;
};

export const getAiAnalysisResult = ( task ) => ( {
	discoverability: task?.result?.discoverability || {},
	suggestions: Array.isArray( task?.result?.suggestions?.suggestions )
		? task.result.suggestions.suggestions
		: [],
} );
