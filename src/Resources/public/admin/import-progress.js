/*
 * The progress of an import on its page (admin/transfer/import.html.twig) while the Messenger worker checks or imports
 * it. The page asks for itself again every few seconds, and shows itself anew once the import's state, or when the
 * worker started it, changed, so the plan or the result shows as soon as there is one. See "Transfer" in
 * docs/spec/admin.md
 */

const LOOK_AGAIN_AFTER = 2000;

const progress = document.querySelector('[data-cms-import-state]');

/**
 * @param {Element} element
 */
const stateOf = (element) => `${element.getAttribute('data-cms-import-state')} ${element.getAttribute('data-cms-import-started')}`;

const lookAgain = async () => {
    try {
        const response = await fetch(window.location.href, { credentials: 'same-origin', headers: { Accept: 'text/html' } });
        if (response.ok) {
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const current = page.querySelector('[data-cms-import-state]');
            if (current !== null && progress !== null && stateOf(current) !== stateOf(progress)) {
                window.location.reload();

                return;
            }
        }
    } catch {
        // Offline for a moment, or the server is busy: it looks again
    }

    window.setTimeout(lookAgain, LOOK_AGAIN_AFTER);
};

if (progress !== null) {
    window.setTimeout(lookAgain, LOOK_AGAIN_AFTER);
}
