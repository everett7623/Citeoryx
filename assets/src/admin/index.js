import { render } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import App from './App';
import './style.css';

const container = document.getElementById( 'citeoryx-admin-app' );
if ( container ) {
	render( <App />, container );
}
