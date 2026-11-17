import './tokens.css';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { createApi } from './api';
import { App } from './App';
import { readConfig } from './config';
import { readMediaLibraryConfig } from './media/config';
import { MediaLibraryApp } from './media/MediaLibraryApp';
import { readPageScopesConfig, readPlacementScopeConfig } from './pageScope/config';
import { PageScopeEditor } from './pageScope/PageScopeEditor';

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

// Edit page scope of each placement in the Placements section of a snippet's overview page
for (const section of document.querySelectorAll<HTMLElement>('[data-cms-page-scopes]')) {
    const config = readPageScopesConfig(section);

    for (const placement of section.querySelectorAll<HTMLElement>('[data-cms-page-scope]')) {
        createRoot(placement).render(
            <StrictMode>
                <PageScopeEditor config={config} placement={readPlacementScopeConfig(placement)} />
            </StrictMode>,
        );
    }
}
