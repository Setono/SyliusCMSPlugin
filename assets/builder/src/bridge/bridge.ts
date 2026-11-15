/**
 * The bridge: the script preview mode injects into the shop page in the builder's preview. It finds blocks by their
 * markers, draws the outlines, the selected block's controls and + between blocks, handles click-to-select and typing in place, and
 * patches blocks the builder re-rendered. It talks to the builder only by postMessage (docs/spec/builder.md#the-bridge).
 */
import type { Editor } from '@tiptap/core';
import { caretAt, fieldAt, startTyping, type Caret, type TypingInPlace } from './inPlace';
import { blockContaining, blockElements, blockHolds, blockNodes, findBlock, rootElement, type BlockRange, type FieldRange } from './markers';
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
.formatting { transform: translateY(-100%); }
.formatting button { font: 600 13px/1 system-ui, sans-serif; }
.formatting button[aria-pressed="true"] { background: rgba(255, 255, 255, 0.35); }
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

/** The marks of rich text typed in place, each with its glyph, as in the right pane's toolbar. */
const MARKS = [
    { mark: 'bold', glyph: '<b>B</b>' },
    { mark: 'italic', glyph: '<i>I</i>' },
    { mark: 'underline', glyph: '<u>U</u>' },
    { mark: 'strike', glyph: '<s>S</s>' },
    { mark: 'code', glyph: '<code>&lt;/&gt;</code>' },
] as const;

export function createBridge(document: Document, post: (message: FromBridge) => void): Bridge {
    const window = document.defaultView;
    let strings: BridgeStrings | null = null;
    let selected: string | null = null;
    let hovered: string | null = null;
    let controls: Controls | null = null;
    let typing: TypingInPlace | null = null;
    // The patches of the block being typed in, held back until the typing ends, so they don't move the caret
    const held = new Map<string, string>();

    post({ type: 'ready' });

    const refresh = (): void => {
        controls?.draw(selected === null ? null : findBlock(document, selected), hovered === null || hovered === selected ? null : findBlock(document, hovered));
    };

    const typeInPlace = (field: FieldRange, caret: Caret | null, at: { x: number; y: number }): void => {
        typing?.stop();

        const started = startTyping(field, {
            caret,
            at,
            onChange: (value) => {
                post({ type: 'edit', key: field.key, field: field.field, value });
                refresh();
            },
            onEnd: () => {
                typing = null;
                controls?.format(null);
                const patches = [...held];
                held.clear();
                patches.forEach(([key, html]) => patch({ key, html }));
                refresh();
            },
        });
        typing = started;
        controls?.format(started.editor === null ? null : { host: started.host, editor: started.editor });
    };

    const onClick = (event: MouseEvent): void => {
        if (strings === null || controls?.contains(event) === true || !(event.target instanceof Node)) {
            return;
        }

        // A click in the field being typed in moves the caret, as in any text field, and follows no link around it
        if (typing?.host.contains(event.target) === true) {
            event.preventDefault();

            return;
        }

        const caret = caretAt(document, event.clientX, event.clientY);
        const field = fieldAt(document, event.target, caret);
        const key = field?.key ?? blockContaining(document, event.target)?.key;
        if (key === undefined) {
            return;
        }

        // In the preview a click selects, so links in blocks don't take the preview away
        event.preventDefault();
        event.stopPropagation();
        post({ type: 'select', key });

        if (field !== null) {
            typeInPlace(field, caret, { x: event.clientX, y: event.clientY });
        }
    };

    // The editor went to the builder, or elsewhere, so the typing ends
    const onBlur = (): void => typing?.stop();

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
            // Undo changes the working copy, which the field being typed in shows only once the typing ends
            typing?.stop();
            post({ type: shortcut });
        }
    };

    document.addEventListener('click', onClick, true);
    document.addEventListener('mouseover', onPointerOver, true);
    document.addEventListener('keydown', onKeyDown);
    window?.addEventListener('scroll', refresh, { passive: true });
    window?.addEventListener('resize', refresh);
    window?.addEventListener('blur', onBlur);

    const patch = ({ key, html }: RenderedBlock): void => {
        const block = findBlock(document, key);
        if (block === null) {
            post({ type: 'missing', key });

            return;
        }

        if (typing !== null && blockHolds(block, typing.host)) {
            held.set(key, html);

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
        if (block !== null && typing !== null && blockHolds(block, typing.host)) {
            typing.stop();
        }

        if (block !== null) {
            [block.start, ...blockNodes(block), block.end].forEach((node) => node.remove());
        }
    };

    return {
        receive(message) {
            switch (message.type) {
                case 'init':
                    strings = message.strings;
                    controls ??= createControls(document, strings, (action, target) => {
                        const key = target === 'hovered' ? hovered : selected;
                        if (key !== null) {
                            post(request(action, key));
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
            typing?.stop();
            document.removeEventListener('click', onClick, true);
            document.removeEventListener('mouseover', onPointerOver, true);
            document.removeEventListener('keydown', onKeyDown);
            window?.removeEventListener('scroll', refresh);
            window?.removeEventListener('resize', refresh);
            window?.removeEventListener('blur', onBlur);
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

/** Rich text being typed in place, which the formatting toolbar works on. */
interface Formatting {
    host: HTMLElement;
    editor: Editor;
}

interface Controls {
    draw(selected: BlockRange | null, hovered: BlockRange | null): void;
    /** Shows the formatting toolbar for rich text typed in place, or hides it. */
    format(formatting: Formatting | null): void;
    /** Whether the event happened on the bridge's own controls. */
    contains(event: Event): boolean;
    dispose(): void;
}

/**
 * The controls: the outlines, the selected block's toolbar, and + above and below both the selected block and the block
 * under the pointer, so there's a + between any two blocks. act() is told which of the two blocks a control is for
 */
function createControls(document: Document, strings: BridgeStrings, act: (action: Action, target: 'selected' | 'hovered') => void): Controls {
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
        button(document, strings.selectParent, ICONS.selectParent, () => act('select_parent', 'selected')),
        button(document, strings.moveUp, ICONS.moveUp, () => act({ direction: 'up' }, 'selected')),
        button(document, strings.moveDown, ICONS.moveDown, () => act({ direction: 'down' }, 'selected')),
        button(document, strings.duplicate, ICONS.duplicate, () => act('duplicate', 'selected')),
        button(document, strings.settings, ICONS.settings, () => act('settings', 'selected')),
        button(document, strings.delete, ICONS.delete, () => act('delete', 'selected')),
    );
    const add = (position: 'before' | 'after', element: HTMLElement, target: 'selected' | 'hovered'): void => {
        const { left, top, width, height } = element.getBoundingClientRect();
        act({ position, x: left + width / 2, y: top + height / 2 }, target);
    };
    const addBefore = button(document, strings.addBefore, ICONS.add, () => add('before', addBefore, 'selected'), 'add');
    const addAfter = button(document, strings.addAfter, ICONS.add, () => add('after', addAfter, 'selected'), 'add');
    const hoverAddBefore = button(document, strings.addBefore, ICONS.add, () => add('before', hoverAddBefore, 'hovered'), 'add');
    const hoverAddAfter = button(document, strings.addAfter, ICONS.add, () => add('after', hoverAddAfter, 'hovered'), 'add');

    let formatting: Formatting | null = null;
    const formatBar = document.createElement('div');
    formatBar.className = 'toolbar formatting';
    formatBar.setAttribute('role', 'toolbar');
    formatBar.setAttribute('aria-label', strings.formatting);
    formatBar.hidden = true;
    const markButtons = MARKS.map(({ mark, glyph }) => {
        const markButton = button(document, strings[mark], glyph, () => formatting?.editor.chain().focus().toggleMark(mark).run(), undefined, false);
        // Keeps the editor's selection, which taking the focus would lose
        markButton.addEventListener('mousedown', (event) => event.preventDefault());
        markButton.setAttribute('aria-pressed', 'false');
        formatBar.append(markButton);

        return { mark, button: markButton };
    });
    const showMarks = (): void => {
        markButtons.forEach(({ mark, button: markButton }) => markButton.setAttribute('aria-pressed', String(formatting?.editor.isActive(mark) === true)));
    };

    layer.append(style, hover, selection, hoverAddBefore, hoverAddAfter, toolbar, addBefore, addAfter, formatBar);
    shadow.append(layer);
    document.body.append(host);

    return {
        draw(selected, hovered) {
            const hoverBox = hovered === null ? null : boxOf(hovered);
            place(hover, hoverBox);
            placeAdds(hoverAddBefore, hoverAddAfter, hoverBox);

            const box = selected === null ? null : boxOf(selected);
            place(selection, box);
            placeAdds(addBefore, addAfter, box);
            toolbar.hidden = box === null;
            if (box !== null) {
                Object.assign(toolbar.style, { left: `${box.right}px`, top: `${Math.max(box.top, 30)}px` });
            }

            formatBar.hidden = formatting === null;
            if (formatting !== null) {
                const field = formatting.host.getBoundingClientRect();
                Object.assign(formatBar.style, { left: `${field.left}px`, top: `${Math.max(field.top, 30)}px` });
            }
        },

        format(next) {
            formatting?.editor.off('transaction', showMarks);
            formatting = next;
            formatting?.editor.on('transaction', showMarks);
            showMarks();
            formatBar.hidden = formatting === null;
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

/** A button with an icon: the paths of an SVG icon, or else HTML of its own. */
function button(document: Document, label: string, icon: string, onClick: () => void, className?: string, svg = true): HTMLButtonElement {
    const element = document.createElement('button');
    element.type = 'button';
    element.setAttribute('aria-label', label);
    element.title = label;
    element.innerHTML = svg ? `<svg viewBox="0 0 16 16" aria-hidden="true">${icon}</svg>` : `<span aria-hidden="true">${icon}</span>`;
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

/** Puts + buttons in the middle of the top and the bottom edge of a block's box, or hides them. */
function placeAdds(before: HTMLElement, after: HTMLElement, box: DOMRect | null): void {
    before.hidden = box === null;
    after.hidden = box === null;
    if (box !== null) {
        const middle = `${box.left + box.width / 2}px`;
        Object.assign(before.style, { left: middle, top: `${box.top}px` });
        Object.assign(after.style, { left: middle, top: `${box.bottom}px` });
    }
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
