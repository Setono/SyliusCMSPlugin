import './tokens.css';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { createApi } from './api';
import { App } from './App';
import { readConfig } from './config';

const element = document.getElementById('cms-builder');

if (element !== null) {
    const config = readConfig(element);

    createRoot(element).render(
        <StrictMode>
            <App config={config} api={createApi(config)} />
        </StrictMode>,
    );
}
