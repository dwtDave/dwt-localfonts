import React from 'react';
import apiFetch from '@wordpress/api-fetch';
import FontManagerApp from './FontManagerApp';

// Set up API fetch with WordPress nonce - MUST be done before rendering
if (window.dwtLocalFonts) {
    apiFetch.use(apiFetch.createNonceMiddleware(window.dwtLocalFonts.nonce));
    apiFetch.use(apiFetch.createRootURLMiddleware(window.dwtLocalFonts.apiUrl));
}

function App(): React.JSX.Element {
    return <FontManagerApp />;
}

export default App;
