/*
 * The Transfer screen's Upload (admin/transfer/index.html.twig). It sends the export zip with fetch, asking for JSON, so
 * the button shows it's uploading and can't be pressed twice while a big zip goes up, and then goes to the page the
 * answer names: the import's page, or back to the screen with why. When that fails, the form posts as it would without
 * this script. See "Transfer" in docs/spec/admin.md
 */

const form = document.querySelector('form[data-cms-import-upload]');

form?.addEventListener('submit', async (event) => {
    event.preventDefault();

    const button = form.querySelector('[type="submit"]');
    button?.setAttribute('disabled', '');
    button?.classList.add('loading');

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const answer = response.ok ? await response.json() : null;
        if (typeof answer?.url === 'string') {
            window.location.assign(answer.url);

            return;
        }
    } catch {
        // Posted as usual below
    }

    form.submit();
});
