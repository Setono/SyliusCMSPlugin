import './tokens.css';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { createApi } from './api';
import { App } from './App';
import { readConfig } from './config';
import { readMediaLibraryConfig } from './media/config';
import { MediaLibraryApp } from './media/MediaLibraryApp';

const element = document.getElementById('cms-builder');

if (element !== null) {
    const config = readConfig(element);

    createRoot(element).render(
        <StrictMode>
            <App config={config} api={createApi(config)} />
        </StrictMode>,
    );
}

// The media library's own screen in the admin
const mediaLibrary = document.getElementById('cms-media-library');

if (mediaLibrary !== null) {
    createRoot(mediaLibrary).render(
        <StrictMode>
            <MediaLibraryApp config={readMediaLibraryConfig(mediaLibrary)} />
        </StrictMode>,
    );
}
