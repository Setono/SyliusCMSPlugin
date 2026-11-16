/*
 * The Share draft dialog and the copy buttons of an entry overview page's Share links section
 * (admin/entry/show/_share_links.html.twig). See "Entry overview page" in docs/spec/admin.md
 *
 * A button with [data-cms-dialog-open], such as the header's Share draft and the section's New, opens the dialog its
 * aria-controls names. A [data-cms-copy] button copies the link in the input it names, and says so for a moment.
 */

const COPIED_FOR = 2000;

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }

    const opener = event.target.closest('[data-cms-dialog-open]');
    if (opener !== null) {
        const dialog = document.getElementById(opener.getAttribute('aria-controls') ?? '');
        if (dialog instanceof HTMLDialogElement) {
            dialog.showModal();
            dialog.querySelector('select, [type="submit"]')?.focus();
        }

        return;
    }

    if (event.target.closest('[data-cms-dialog-close]') !== null) {
        event.target.closest('dialog')?.close();

        return;
    }

    const copy = event.target.closest('[data-cms-copy]');
    if (copy !== null) {
        copyLink(copy);
    }
});

/**
 * Copies the link to the clipboard, or, where the browser doesn't offer the clipboard to the page, through the
 * selection, which stays on the link so it can be copied by hand
 */
async function copyLink(button) {
    const input = document.getElementById(button.getAttribute('data-cms-copy') ?? '');
    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    input.select();
    try {
        await navigator.clipboard.writeText(input.value);
    } catch {
        document.execCommand('copy');
    }

    const label = button.querySelector('[data-cms-copy-label]');
    if (label === null) {
        return;
    }

    label.dataset.label ??= label.textContent;
    label.textContent = button.getAttribute('data-copied');
    clearTimeout(Number(label.dataset.timeout));
    label.dataset.timeout = String(setTimeout(() => {
        label.textContent = label.dataset.label;
    }, COPIED_FOR));
}
