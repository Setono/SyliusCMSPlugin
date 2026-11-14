/**
 * The bridge's entry point, built to src/Resources/public/builder/bridge.js. Preview mode injects it into the shop page
 * in the builder's preview iframe, which loads from the admin's own host, so the builder and the page share an origin.
 */
import { createBridge } from './bridge';
import { envelope, openEnvelope, type ToBridge } from './protocol';

const builder = window.parent;

// Outside the builder's iframe there's nobody to talk to, so the page stays as it is
if (builder !== window) {
    const origin = window.location.origin;
    const bridge = createBridge(document, (message) => builder.postMessage(envelope(message), origin));

    window.addEventListener('message', (event) => {
        if (event.source !== builder || event.origin !== origin) {
            return;
        }

        const message = openEnvelope<ToBridge>(event.data);
        if (message !== null) {
            bridge.receive(message);
        }
    });
}
