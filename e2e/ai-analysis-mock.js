const taskId = '11111111-1111-4111-8111-111111111111';

const response = ( data ) => ( {
	success: true,
	data,
} );

const task = ( contentId, status, extra = {} ) => ( {
	task_id: taskId,
	content_id: contentId,
	status,
	created_at: '2026-08-20T10:00:00+00:00',
	updated_at: '2026-08-20T10:00:01+00:00',
	reused: false,
	...extra,
} );

const installAiAnalysisMock = async ( page ) => {
	let pollCount = 0;

	await page.route(
		( url ) =>
			decodeURIComponent( url.href ).includes(
				'/citeoryx/v1/integrations/ai/'
			),
		async ( route ) => {
			const request = route.request();
			const url = new URL( request.url() );
			const decodedUrl = decodeURIComponent( request.url() );
			const send = ( data, status = 200 ) =>
				route.fulfill( {
					status,
					contentType: 'application/json',
					body: JSON.stringify( response( data ) ),
				} );

			if ( decodedUrl.includes( '/integrations/ai/availability' ) ) {
				await send( {
					provider: 'openai_compatible',
					enabled: true,
					configured: true,
				} );
				return;
			}

			const contentId = Number(
				decodedUrl.match( /\/integrations\/ai\/analyze\/(\d+)/ )?.[ 1 ]
			);
			if ( 'POST' === request.method() ) {
				await send( task( contentId, 'queued' ), 202 );
				return;
			}

			if ( ! url.searchParams.has( 'task_id' ) ) {
				await send( {
					task_id: '',
					content_id: contentId,
					status: 'idle',
					created_at: '',
					updated_at: '',
					reused: false,
				} );
				return;
			}

			pollCount += 1;
			await send(
				task( contentId, 'completed', {
					result: {
						content_id: contentId,
						discoverability: {
							configured: true,
							score: 84,
							confidence: 'high',
							summary: 'E2E AI analysis completed.',
							strengths: [ 'Clear scope and structure.' ],
							weaknesses: [ 'Needs more cited evidence.' ],
						},
						suggestions: {
							configured: true,
							suggestions: [
								{
									category: 'content',
									priority: 'high',
									title: 'Add cited evidence',
									description:
										'Add one primary source for the main claim.',
								},
							],
						},
					},
				} )
			);
		}
	);

	return {
		getPollCount: () => pollCount,
		taskId,
	};
};

module.exports = { installAiAnalysisMock };
