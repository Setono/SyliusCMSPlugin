/*
 * The Edit dialogs of the Presets screen (admin/preset/index.html.twig). See "Presets" in docs/spec/admin.md
 *
 * A button with [data-cms-dialog-open] opens the dialog its aria-controls names, with the focus in its first field, and
 * a [data-cms-dialog-close] button closes the dialog it's in. Esc closes it too, as dialogs do.
 */

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }

    const opener = event.target.closest('[data-cms-dialog-open]');
    if (opener !== null) {
        const dialog = document.getElementById(opener.getAttribute('aria-controls') ?? '');
        if (dialog instanceof HTMLDialogElement) {
            dialog.showModal();
            dialog.querySelector('input:not([type="hidden"]), textarea')?.focus();
        }

        return;
    }

    if (event.target.closest('[data-cms-dialog-close]') !== null) {
        event.target.closest('dialog')?.close();
    }
});
