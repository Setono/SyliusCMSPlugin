/**
 * The bridge: the script preview mode injects into the shop page in the builder's preview. It finds blocks by their
 * markers, draws the outlines and the selected block's controls, handles click-to-select, and patches blocks the
 * builder re-rendered. It talks to the builder only by postMessage (docs/spec/builder.md#the-bridge).
 */
import { blockContaining, blockElements, blockNodes, findBlock, rootElement, type BlockRange } from './markers';
import { morphBlock, parseHtml } from './morph';
import { shortcutOf } from '../shortcuts';
import type { BridgeStrings, FromBridge, RenderedBlock, ToBridge } from './protocol';

export interface Bridge {
    receive(message: ToBridge): void;
    dispose(): void;
}

/** The bridge's controls live in a shadow root, so the shop's CSS leaves them alone and theirs stays out of the shop. */
const STYLE = `
:host { all: initial; position: fixed; top: 0; left: 0; z-index: 2147483647; }
[hidden] { display: none !important; }
.outline { position: fixed; box-sizing: border-box; pointer-events: none; border: 2px solid #2f6fed; border-radius: 2px; }
.outline[data-outline="hover"] { border-style: dashed; border-color: rgba(47, 111, 237, 0.6); }
.toolbar { position: fixed; display: flex; gap: 2px; padding: 2px; border-radius: 4px; background: #2f6fed; transform: translate(-100%, -100%); pointer-events: auto; }
button { all: unset; box-sizing: border-box; display: grid; place-items: center; width: 24px; height: 24px; border-radius: 3px; color: #fff; cursor: pointer; }
.toolbar button:hover, .toolbar button:focus-visible { background: rgba(255, 255, 255, 0.2); }
.add { position: fixed; border-radius: 50%; background: #2f6fed; box-shadow: 0 0 0 2px #fff; transform: translate(-50%, -50%); pointer-events: auto; }
.add:hover, .add:focus-visible { background: #1d4fc4; }
svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round; }
`;

const ICONS = {
    selectParent: '<path d="M6 10 2.5 6.5 6 3M2.5 6.5H10a3.5 3.5 0 0 1 3.5 3.5v3"/>',
    moveUp: '<path d="M8 13V3M4 7l4-4 4 4"/>',
    moveDown: '<path d="M8 3v10M4 9l4 4 4-4"/>',
    duplicate: '<path d="M5.5 5.5h8v8h-8zM10.5 5.5v-3h-8v8h3"/>',
    settings: '<path d="M2.5 4.5h11M2.5 11.5h11"/><circle cx="6" cy="4.5" r="1.75" fill="#2f6fed"/><circle cx="10" cy="11.5" r="1.75" fill="#2f6fed"/>',
    delete: '<path d="M2.5 4h11M6.5 4V2.5h3V4M4 4l.7 9.5h6.6L12 4"/>',
    add: '<path d="M8 3v10M3 8h10"/>',
};

export function createBridge(document: Document, post: (message: FromBridge) => void): Bridge {
    const window = document.defaultView;
    let strings: BridgeStrings | null = null;
    let selected: string | null = null;
    let hovered: string | null = null;
    let controls: Controls | null = null;

    post({ type: 'ready' });

    const refresh = (): void => {
        controls?.draw(selected === null ? null : findBlock(document, selected), hovered === null || hovered === selected ? null : findBlock(document, hovered));
    };

    const onClick = (event: MouseEvent): void => {
        if (strings === null || controls?.contains(event) === true) {
            return;
        }

        const block = event.target instanceof Node ? blockContaining(document, event.target) : null;
        if (block === null) {
            return;
        }

        // In the preview a click selects, so links in blocks don't take the preview away
        event.preventDefault();
        event.stopPropagation();
        post({ type: 'select', key: block.key });
    };

    const onPointerOver = (event: MouseEvent): void => {
        if (strings === null || controls?.contains(event) === true) {
            return;
        }

        hovered = event.target instanceof Node ? (blockContaining(document, event.target)?.key ?? null) : null;
        refresh();
    };

    // Undo and redo work wherever the editor is, so the keys pressed in the page go to the builder
    const onKeyDown = (event: KeyboardEvent): void => {
        const shortcut = strings === null || event.defaultPrevented ? null : shortcutOf(event);
        if (shortcut !== null) {
            event.preventDefault();
            post({ type: shortcut });
        }
    };

    document.addEventListener('click', onClick, true);
    document.addEventListener('mouseover', onPointerOver, true);
    document.addEventListener('keydown', onKeyDown);
    window?.addEventListener('scroll', refresh, { passive: true });
    window?.addEventListener('resize', refresh);

    const patch = ({ key, html }: RenderedBlock): void => {
        const block = findBlock(document, key);
        if (block === null) {
            post({ type: 'missing', key });

            return;
        }

        rendered(morphBlock(block, html));
    };

    const insert = (block: RenderedBlock, previous: string[], next: string[]): void => {
        const before = previous.map((key) => findBlock(document, key)).find((found) => found !== null);
        const after = before === undefined ? next.map((key) => findBlock(document, key)).find((found) => found !== null) : undefined;
        const parent = before?.end.parentNode ?? after?.start.parentNode ?? null;
        if (parent === null) {
            post({ type: 'missing', key: block.key });

            return;
        }

        // A block that's in the page already, such as one moved out of its parent, moves to its spot
        remove(block.key);

        const anchor = before === undefined ? (after?.start ?? null) : before.end.nextSibling;
        for (const node of parseHtml(document, block.html)) {
            parent.insertBefore(node, anchor);
        }

        const inserted = findBlock(document, block.key);
        if (inserted !== null) {
            rendered(inserted);
        }
    };

    const remove = (key: string): void => {
        const block = findBlock(document, key);
        if (block !== null) {
            [block.start, ...blockNodes(block), block.end].forEach((node) => node.remove());
        }
    };

    return {
        receive(message) {
            switch (message.type) {
                case 'init':
                    strings = message.strings;
                    controls ??= createControls(document, strings, (action) => {
                        if (selected !== null) {
                            post(request(action, selected));
                        }
                    });
                    break;
                case 'select': {
                    selected = message.key;
                    const block = selected === null ? null : findBlock(document, selected);
                    const root = block === null ? null : rootElement(block);
                    if (typeof root?.scrollIntoView === 'function') {
                        root.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                    }
                    break;
                }
                case 'patch':
                    message.blocks.forEach(patch);
                    break;
                case 'insert':
                    insert(message.block, message.previous, message.next);
                    break;
                case 'remove':
                    remove(message.key);
                    break;
            }

            refresh();
        },

        dispose() {
            document.removeEventListener('click', onClick, true);
            document.removeEventListener('mouseover', onPointerOver, true);
            document.removeEventListener('keydown', onKeyDown);
            window?.removeEventListener('scroll', refresh);
            window?.removeEventListener('resize', refresh);
            controls?.dispose();
            controls = null;
            strings = null;
        },
    };
}

/** Tells the block's storefront JavaScript, such as a carousel's, that it was patched in. A public contract. */
function rendered(block: BlockRange): void {
    const root = rootElement(block);
    const CustomEventClass = root?.ownerDocument.defaultView?.CustomEvent ?? CustomEvent;

    root?.dispatchEvent(new CustomEventClass('cms:render', { bubbles: true, detail: { key: block.key, type: block.type } }));
}

type Action = 'select_parent' | 'settings' | 'delete' | 'duplicate' | { direction: 'up' | 'down' } | { position: 'before' | 'after'; x: number; y: number };

/** What the builder is asked to do with the selected block. */
function request(action: Action, key: string): FromBridge {
    if (typeof action === 'string') {
        return { type: action, key };
    }

    return 'direction' in action ? { type: 'move', key, direction: action.direction } : { type: 'add', key, ...action };
}

interface Controls {
    draw(selected: BlockRange | null, hovered: BlockRange | null): void;
    /** Whether the event happened on the bridge's own controls. */
    contains(event: Event): boolean;
    dispose(): void;
}

function createControls(document: Document, strings: BridgeStrings, act: (action: Action) => void): Controls {
    const host = document.createElement('div');
    host.setAttribute('data-cms-bridge', '');
    const shadow = host.attachShadow({ mode: 'open' });

    const layer = document.createElement('div');
    const style = document.createElement('style');
    style.textContent = STYLE;
    const hover = outline(document, 'hover');
    const selection = outline(document, 'selected');
    const toolbar = document.createElement('div');
    toolbar.className = 'toolbar';
    toolbar.setAttribute('role', 'toolbar');
    toolbar.append(
        button(document, strings.selectParent, ICONS.selectParent, () => act('select_parent')),
        button(document, strings.moveUp, ICONS.moveUp, () => act({ direction: 'up' })),
        button(document, strings.moveDown, ICONS.moveDown, () => act({ direction: 'down' })),
        button(document, strings.duplicate, ICONS.duplicate, () => act('duplicate')),
        button(document, strings.settings, ICONS.settings, () => act('settings')),
        button(document, strings.delete, ICONS.delete, () => act('delete')),
    );
    const add = (position: 'before' | 'after', element: HTMLElement): void => {
        const { left, top, width, height } = element.getBoundingClientRect();
        act({ position, x: left + width / 2, y: top + height / 2 });
    };
    const addBefore = button(document, strings.addBefore, ICONS.add, () => add('before', addBefore), 'add');
    const addAfter = button(document, strings.addAfter, ICONS.add, () => add('after', addAfter), 'add');

    layer.append(style, hover, selection, toolbar, addBefore, addAfter);
    shadow.append(layer);
    document.body.append(host);

    return {
        draw(selected, hovered) {
            place(hover, hovered === null ? null : boxOf(hovered));

            const box = selected === null ? null : boxOf(selected);
            place(selection, box);
            [toolbar, addBefore, addAfter].forEach((element) => {
                element.hidden = box === null;
            });

            if (box !== null) {
                Object.assign(toolbar.style, { left: `${box.right}px`, top: `${Math.max(box.top, 30)}px` });
                const middle = `${box.left + box.width / 2}px`;
                Object.assign(addBefore.style, { left: middle, top: `${box.top}px` });
                Object.assign(addAfter.style, { left: middle, top: `${box.bottom}px` });
            }
        },

        contains(event) {
            return event.composedPath().includes(host);
        },

        dispose() {
            host.remove();
        },
    };
}

function outline(document: Document, kind: 'hover' | 'selected'): HTMLElement {
    const element = document.createElement('div');
    element.className = 'outline';
    element.setAttribute('data-outline', kind);
    element.hidden = true;

    return element;
}

function button(document: Document, label: string, icon: string, onClick: () => void, className?: string): HTMLButtonElement {
    const element = document.createElement('button');
    element.type = 'button';
    element.setAttribute('aria-label', label);
    element.title = label;
    element.innerHTML = `<svg viewBox="0 0 16 16" aria-hidden="true">${icon}</svg>`;
    if (className !== undefined) {
        element.className = className;
    }
    element.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        onClick();
    });

    return element;
}

function place(element: HTMLElement, box: DOMRect | null): void {
    element.hidden = box === null;
    if (box !== null) {
        Object.assign(element.style, { left: `${box.left}px`, top: `${box.top}px`, width: `${box.width}px`, height: `${box.height}px` });
    }
}

/** The box around the block's elements, in the viewport, or null when it has none. */
function boxOf(block: BlockRange): DOMRect | null {
    const rects = blockElements(block).map((element) => element.getBoundingClientRect());
    if (rects.length === 0) {
        return null;
    }

    const left = Math.min(...rects.map((rect) => rect.left));
    const top = Math.min(...rects.map((rect) => rect.top));
    const right = Math.max(...rects.map((rect) => rect.right));
    const bottom = Math.max(...rects.map((rect) => rect.bottom));

    return new DOMRect(left, top, right - left, bottom - top);
}
