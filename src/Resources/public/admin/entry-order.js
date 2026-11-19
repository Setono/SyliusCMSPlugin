/*
 * The list of a content type ordered by position (admin/entry/index.html.twig), such as FAQ items. Editors drag its rows
 * into a new order, or move them with the arrows each row gets, and the order of the rows on the page is saved at once:
 * their UUIDs, in order, go to the [data-cms-entry-order] element's data-url, with its CSRF token. The positions are
 * on-save settings, so they take effect without publishing. See "Entry lists" in docs/spec/admin.md
 */

const order = document.querySelector('[data-cms-entry-order]');
const status = order?.querySelector('[data-cms-entry-order-status]') ?? null;

/** The rows of the list, each with the UUID of its entry */
const rows = order === null ? [] : [...document.querySelectorAll('tr')].filter((row) => row.querySelector('[data-entry]') !== null);
const body = rows[0]?.parentElement ?? null;

/** The row being dragged */
let dragged = null;

/** The order the rows were saved in last, so a drop that changes nothing saves nothing */
let saved = uuids();

for (const row of rows) {
    row.draggable = true;
    row.classList.add('cms-entry-order__row');

    const title = row.querySelector('[data-entry]');
    const name = title?.querySelector('strong')?.textContent?.trim() ?? '';
    const arrows = document.createElement('div');
    arrows.className = 'ui mini icon buttons cms-entry-order__arrows';
    arrows.append(arrow('up', order.dataset.moveUp ?? '', name), arrow('down', order.dataset.moveDown ?? '', name));
    title?.prepend(arrows);
}
updateArrows();

document.addEventListener('click', (event) => {
    const button = event.target instanceof Element ? event.target.closest('[data-cms-entry-order-move]') : null;
    const row = button?.closest('tr');
    if (!(button instanceof HTMLButtonElement) || !(row instanceof HTMLElement) || !rows.includes(row)) {
        return;
    }

    const up = button.dataset.cmsEntryOrderMove === 'up';
    const sibling = up ? row.previousElementSibling : row.nextElementSibling;
    if (sibling !== null) {
        sibling.insertAdjacentElement(up ? 'beforebegin' : 'afterend', row);
        updateArrows();
        save();
    }

    // The arrow keeps the focus, or at the end of the list, the other one
    (button.disabled ? row.querySelector(`[data-cms-entry-order-move="${up ? 'down' : 'up'}"]`) : button)?.focus();
});

document.addEventListener('dragstart', (event) => {
    const row = event.target instanceof Element ? event.target.closest('tr') : null;
    if (row === null || !rows.includes(row)) {
        return;
    }

    dragged = row;
    row.classList.add('cms-entry-order__row--dragged');
    event.dataTransfer.effectAllowed = 'move';
    // Some browsers only start a drag that carries data
    event.dataTransfer.setData('text/plain', row.querySelector('[data-entry]')?.dataset.entry ?? '');
});

document.addEventListener('dragover', (event) => {
    const row = event.target instanceof Element ? event.target.closest('tr') : null;
    if (dragged === null || row === null || !rows.includes(row)) {
        return;
    }

    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    if (row === dragged) {
        return;
    }

    // Over the top half of a row, the dragged one goes before it, and over the bottom half, after it
    const { top, height } = row.getBoundingClientRect();
    row.insertAdjacentElement(event.clientY < top + height / 2 ? 'beforebegin' : 'afterend', dragged);
    updateArrows();
});

document.addEventListener('drop', (event) => {
    if (dragged !== null) {
        event.preventDefault();
    }
});

document.addEventListener('dragend', () => {
    if (dragged === null) {
        return;
    }

    dragged.classList.remove('cms-entry-order__row--dragged');
    dragged = null;
    save();
});

/**
 * The UUIDs of the entries in the order their rows are in
 */
function uuids() {
    return body === null ? [] : [...body.querySelectorAll('[data-entry]')].map((title) => title.dataset.entry ?? '');
}

/**
 * Saves the order the rows are in, unless it's the one saved last
 */
async function save() {
    const entries = uuids();
    if (entries.join() === saved.join()) {
        return;
    }

    const data = new FormData();
    data.append('_csrf_token', order.dataset.csrfToken ?? '');
    for (const uuid of entries) {
        data.append('entries[]', uuid);
    }

    announce(order.dataset.saving);
    try {
        const response = await fetch(order.dataset.url ?? '', { method: 'POST', body: data, headers: { Accept: 'application/json' } });
        if (!response.ok) {
            throw new Error(`The order wasn't saved: ${response.status}`);
        }

        saved = entries;
        announce(order.dataset.saved);
    } catch {
        announce(order.dataset.failed);
    }
}

function announce(message) {
    if (status !== null) {
        status.textContent = message ?? '';
    }
}

function arrow(direction, label, name) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'ui button';
    button.dataset.cmsEntryOrderMove = direction;
    button.title = label;
    button.setAttribute('aria-label', `${label}: ${name}`);
    button.innerHTML = `<i class="arrow ${direction} icon"></i>`;

    return button;
}

/**
 * The first row can't move up, and the last can't move down
 */
function updateArrows() {
    const ordered = body === null ? [] : [...body.children].filter((row) => rows.includes(row));
    ordered.forEach((row, index) => {
        row.querySelector('[data-cms-entry-order-move="up"]')?.toggleAttribute('disabled', index === 0);
        row.querySelector('[data-cms-entry-order-move="down"]')?.toggleAttribute('disabled', index === ordered.length - 1);
    });
}
