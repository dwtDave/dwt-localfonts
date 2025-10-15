import { createRoot } from 'react-dom/client';
import App from './App';
import './index.css';

// Vite HMR is handled automatically in development

const rootEl = document.getElementById('dwt-local-fonts-react-app');
if (rootEl) {
    const root = createRoot(rootEl);
    root.render(<App />);
} else {
    console.error('Root element #dwt-local-fonts-react-app not found');
}