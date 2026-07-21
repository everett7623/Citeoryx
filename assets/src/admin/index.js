import { render } from '@wordpress/element';
import App from './App';
import './style.css';

const container = document.getElementById( 'citeoryx-admin-app' );
if ( container ) {
	render( <App />, container );
}
