/*
 * The Order… dialogs of the slots in the Placements section of a snippet's overview page
 * (admin/entry/show/_slot_order.html.twig). See "Placements" in docs/spec/admin.md
 *
 * A [data-cms-slot-order-open] button opens the dialog its aria-controls names, with the placements in the order the
 * page shows. Each placement is dragged into order, or moved with its arrows. The list holds a hidden placements[]
 * input in each item, so the form posts the UUIDs in the order the items are in. Cancel and Esc close it unsaved.
 */

/** The order each list had when the page loaded, which opening its dialog brings back */
const loaded = new WeakMap();

/** The item being dragged */
let dragged = null;

for (const list of document.querySelectorAll('[data-cms-slot-order-list]')) {
    loaded.set(list, [...list.children]);
    updateArrows(list);
}

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }

    const opener = event.target.closest('[data-cms-slot-order-open]');
    if (opener !== null) {
        const dialog = document.getElementById(opener.getAttribute('aria-controls') ?? '');
        if (dialog instanceof HTMLDialogElement) {
            reset(dialog);
            dialog.showModal();
        }

        return;
    }

    const dialog = event.target.closest('[data-cms-slot-order]');
    if (!(dialog instanceof HTMLDialogElement)) {
        return;
    }

    if (event.target.closest('[data-cms-dialog-close]') !== null) {
        dialog.close();

        return;
    }

    const arrow = event.target.closest('[data-cms-slot-order-move]');
    const item = arrow?.closest('[data-ordered-placement]');
    if (arrow instanceof HTMLButtonElement && item instanceof HTMLElement) {
        const up = arrow.getAttribute('data-cms-slot-order-move') === 'up';
        const sibling = up ? item.previousElementSibling : item.nextElementSibling;
        if (sibling !== null) {
            sibling.insertAdjacentElement(up ? 'beforebegin' : 'afterend', item);
            updateArrows(item.parentElement);
        }

        // The arrow keeps the focus, or at the end of the list, the other one
        (arrow.disabled ? item.querySelector(`[data-cms-slot-order-move="${up ? 'down' : 'up'}"]`) : arrow)?.focus();
    }
});

document.addEventListener('dragstart', (event) => {
    const item = event.target instanceof Element ? event.target.closest('[data-cms-slot-order-list] > [data-ordered-placement]') : null;
    if (item === null) {
        return;
    }

    dragged = item;
    item.classList.add('cms-slot-order__item--dragged');
    event.dataTransfer.effectAllowed = 'move';
    // Some browsers only start a drag that carries data
    event.dataTransfer.setData('text/plain', item.getAttribute('data-ordered-placement') ?? '');
});

document.addEventListener('dragover', (event) => {
    const item = event.target instanceof Element ? event.target.closest('[data-ordered-placement]') : null;
    if (dragged === null || item === null || item.parentElement !== dragged.parentElement) {
        return;
    }

    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    if (item === dragged) {
        return;
    }

    // Over the top half of an item, the dragged one goes before it, and over the bottom half, after it
    const { top, height } = item.getBoundingClientRect();
    item.insertAdjacentElement(event.clientY < top + height / 2 ? 'beforebegin' : 'afterend', dragged);
    updateArrows(item.parentElement);
});

document.addEventListener('drop', (event) => {
    if (dragged !== null) {
        event.preventDefault();
    }
});

document.addEventListener('dragend', () => {
    dragged?.classList.remove('cms-slot-order__item--dragged');
    dragged = null;
});

/**
 * Puts the dialog's placements back in the order the page shows
 */
function reset(dialog) {
    const list = dialog.querySelector('[data-cms-slot-order-list]');
    if (list === null) {
        return;
    }

    list.append(...(loaded.get(list) ?? []));
    updateArrows(list);
}

/**
 * The first placement can't move up, and the last can't move down
 */
function updateArrows(list) {
    const items = [...list.children];
    items.forEach((item, index) => {
        item.querySelector('[data-cms-slot-order-move="up"]')?.toggleAttribute('disabled', index === 0);
        item.querySelector('[data-cms-slot-order-move="down"]')?.toggleAttribute('disabled', index === items.length - 1);
    });
}
