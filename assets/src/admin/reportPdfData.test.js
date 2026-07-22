import { buildReportSections } from './reportPdfData';

const report = {
	generated_at: '2026-07-22 15:00:00',
	content: {
		total: 12,
		average_health_score: 81.5,
		average_ai_readiness_score: 73,
		status_counts: [ { label: 'healthy', count: 7 } ],
	},
	issues: {
		open_total: 2,
		severity_counts: [ { label: 'high', count: 2 } ],
		category_counts: [ { label: 'content', count: 2 } ],
		top_items: [ { title: '需要补充中文证据', priority_score: 8.4 } ],
	},
	scans: {
		recent: [
			{
				scan_type: 'full',
				status: 'completed',
				started_at: '2026-07-22',
			},
		],
	},
	performance: {
		clicks: 123,
		impressions: 4567,
		history: [ { metric_date: '2026-07-21', clicks: 5, impressions: 90 } ],
		dimensions: {
			queries: [
				{
					label: '内容健康',
					source: 'google_search_console',
					clicks: 3,
					impressions: 40,
				},
			],
			countries: [],
			devices: [],
		},
	},
};

describe( 'buildReportSections', () => {
	it( 'maps the bounded report contract into printable sections', () => {
		const sections = buildReportSections( report );

		expect( sections ).toHaveLength( 10 );
		expect( sections[ 0 ].items ).toHaveLength( 6 );
		expect( sections[ 5 ].rows[ 0 ].name ).toContain( 'Google' );
		expect( sections[ 8 ].rows[ 0 ].title ).toBe( '需要补充中文证据' );
	} );

	it( 'keeps empty dimensions as empty arrays for a stable layout', () => {
		const sections = buildReportSections( report );

		expect( sections[ 6 ].rows ).toEqual( [] );
		expect( sections[ 7 ].rows ).toEqual( [] );
	} );
} );
