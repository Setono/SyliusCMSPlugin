import { fireEvent, within } from '@testing-library/dom';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { createBridge, type Bridge } from '../../src/bridge/bridge';
import type { BridgeStrings, FromBridge } from '../../src/bridge/protocol';

const strings: BridgeStrings = {
    selectParent: 'Select parent',
    settings: 'Settings',
    delete: 'Delete block',
    addBefore: 'Add block above',
    addAfter: 'Add block below',
    moveUp: 'Move up',
    moveDown: 'Move down',
    duplicate: 'Duplicate',
    formatting: 'Formatting',
    bold: 'Bold',
    italic: 'Italic',
    underline: 'Underline',
    strike: 'Strikethrough',
    code: 'Code',
};

function marked(key: string, html: string, type = 'heading'): string {
    return `<!--cms:block ${key} ${type}-->${html}<!--/cms:block ${key}-->`;
}

/** The bridge's own controls, which it draws in a shadow root so the shop's CSS leaves them alone. */
function controls(): HTMLElement {
    const root = document.querySelector('[data-cms-bridge]')?.shadowRoot?.firstElementChild;
    if (!(root instanceof HTMLElement)) {
        throw new Error('The bridge has drawn no controls');
    }

    return root;
}

function outline(): HTMLElement | null {
    return controls().querySelector('[data-outline="selected"]');
}

describe('the bridge', () => {
    let posted: FromBridge[];
    let bridge: Bridge;
    let scrolledTo: Element[];

    beforeEach(() => {
        document.body.innerHTML = `<header><a href="/">Shop</a></header><main>${marked('a', '<h2 class="cms-heading">First</h2>')}${marked(
            'b',
            `<section class="cms-section"><a href="/elsewhere">${marked('c', '<h2 class="cms-heading">Inside</h2>')}</a></section>`,
            'group',
        )}${marked('d', '<h2 class="cms-heading">Last</h2>')}</main>`;
        posted = [];
        scrolledTo = [];
        Element.prototype.scrollIntoView = function (this: Element) {
            scrolledTo.push(this);
        };
        bridge = createBridge(document, (message) => posted.push(message));
    });

    afterEach(() => {
        bridge.dispose();
    });

    it('should tell the builder it is ready', () => {
        expect(posted).toEqual([{ type: 'ready' }]);
    });

    describe('once the builder has set it up', () => {
        beforeEach(() => {
            bridge.receive({ type: 'init', strings });
            posted = [];
        });

        it('should select the innermost block a click lands in, instead of following its link', () => {
            const click = new MouseEvent('click', { bubbles: true, cancelable: true });

            document.querySelectorAll('h2')[1]?.dispatchEvent(click);

            expect(posted).toEqual([{ type: 'select', key: 'c' }]);
            expect(click.defaultPrevented).toBe(true);
        });

        it('should leave clicks outside the blocks to the page', () => {
            const click = new MouseEvent('click', { bubbles: true, cancelable: true });

            document.querySelector('header a')?.dispatchEvent(click);

            expect(posted).toEqual([]);
            expect(click.defaultPrevented).toBe(false);
        });

        it('should outline the block the builder selects and scroll to it', () => {
            bridge.receive({ type: 'select', key: 'd' });

            expect(outline()).toBeVisible();
            expect(scrolledTo).toEqual([document.querySelectorAll('h2')[2]]);
        });

        it('should drop the outline when the builder selects nothing', () => {
            bridge.receive({ type: 'select', key: 'd' });
            bridge.receive({ type: 'select', key: null });

            expect(outline()).not.toBeVisible();
            expect(within(controls()).queryByRole('toolbar')).toBeNull();
        });

        it("should give the selected block a toolbar with select parent, settings and delete, which ask the builder", () => {
            bridge.receive({ type: 'select', key: 'c' });
            const toolbar = within(controls()).getByRole('toolbar');

            fireEvent.click(within(toolbar).getByRole('button', { name: 'Select parent' }));
            fireEvent.click(within(toolbar).getByRole('button', { name: 'Settings' }));
            fireEvent.click(within(toolbar).getByRole('button', { name: 'Delete block' }));

            expect(posted).toEqual([
                { type: 'select_parent', key: 'c' },
                { type: 'settings', key: 'c' },
                { type: 'delete', key: 'c' },
            ]);
        });

        it("should offer move up, move down and duplicate in the selected block's toolbar, which ask the builder", () => {
            bridge.receive({ type: 'select', key: 'a' });
            const toolbar = within(controls()).getByRole('toolbar');

            fireEvent.click(within(toolbar).getByRole('button', { name: 'Move up' }));
            fireEvent.click(within(toolbar).getByRole('button', { name: 'Move down' }));
            fireEvent.click(within(toolbar).getByRole('button', { name: 'Duplicate' }));

            expect(posted).toEqual([
                { type: 'move', key: 'a', direction: 'up' },
                { type: 'move', key: 'a', direction: 'down' },
                { type: 'duplicate', key: 'a' },
            ]);
        });

        it('should pass ⌘Z and ⇧⌘Z pressed in the page on to the builder, which undoes and redoes', () => {
            const undo = new KeyboardEvent('keydown', { key: 'z', metaKey: true, bubbles: true, cancelable: true });
            const redo = new KeyboardEvent('keydown', { key: 'z', ctrlKey: true, shiftKey: true, bubbles: true, cancelable: true });
            const other = new KeyboardEvent('keydown', { key: 'z', bubbles: true, cancelable: true });

            document.querySelector('h2')?.dispatchEvent(undo);
            document.body.dispatchEvent(redo);
            document.body.dispatchEvent(other);

            expect(posted).toEqual([{ type: 'undo' }, { type: 'redo' }]);
            expect(undo.defaultPrevented).toBe(true);
            expect(other.defaultPrevented).toBe(false);
        });

        it('should pass Esc pressed in the page on to the builder, which leaves, but not Esc pressed in a field or that the page handled', () => {
            document.body.insertAdjacentHTML('beforeend', '<form><input name="q"><textarea></textarea><select><option>A</option></select></form><div contenteditable="true">Note</div>');
            const escape = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
            const handled = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
            const withModifier = new KeyboardEvent('keydown', { key: 'Escape', altKey: true, bubbles: true, cancelable: true });

            document.querySelector('h2')?.dispatchEvent(escape);
            for (const field of document.querySelectorAll('input, textarea, select, [contenteditable]')) {
                field.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }));
            }
            handled.preventDefault();
            document.body.dispatchEvent(handled);
            document.body.dispatchEvent(withModifier);

            expect(posted).toEqual([{ type: 'leave' }]);
            expect(escape.defaultPrevented).toBe(true);
            expect(withModifier.defaultPrevented).toBe(false);
        });

        it('should offer + above and below the selected block, which asks the builder for the block picker at that spot', () => {
            bridge.receive({ type: 'select', key: 'a' });

            fireEvent.click(within(controls()).getByRole('button', { name: 'Add block below' }));
            fireEvent.click(within(controls()).getByRole('button', { name: 'Add block above' }));

            // With where the + is in the page's viewport, so the builder can open the picker next to it
            expect(posted).toEqual([
                { type: 'add', key: 'a', position: 'after', x: 0, y: 0 },
                { type: 'add', key: 'a', position: 'before', x: 0, y: 0 },
            ]);
        });

        it('should offer + above and below the block under the pointer too, so there is one between any two blocks', () => {
            bridge.receive({ type: 'select', key: 'a' });
            fireEvent.mouseOver(document.querySelectorAll('h2')[2]!);

            const hoverAdds = within(controls()).getAllByRole('button', { name: 'Add block above' });
            expect(hoverAdds).toHaveLength(2);
            // The hovered block's come first, under the selected block's where they overlap
            fireEvent.click(hoverAdds[0]!);
            fireEvent.click(within(controls()).getAllByRole('button', { name: 'Add block below' })[0]!);

            expect(posted).toEqual([
                { type: 'add', key: 'd', position: 'before', x: 0, y: 0 },
                { type: 'add', key: 'd', position: 'after', x: 0, y: 0 },
            ]);
        });

        it('should only outline and select blocks while the working copy is read-only, as while a scheduled publish locks it', () => {
            bridge.receive({ type: 'init', strings, readOnly: true });
            bridge.receive({ type: 'select', key: 'a' });
            fireEvent.mouseOver(document.querySelectorAll('h2')[2]!);

            expect(outline()).toBeVisible();
            expect(within(controls()).queryByRole('toolbar')).toBeNull();
            expect(within(controls()).queryAllByRole('button', { name: 'Add block above' })).toHaveLength(0);
            expect(within(controls()).queryAllByRole('button', { name: 'Add block below' })).toHaveLength(0);

            document.querySelectorAll('h2')[0]?.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
            expect(posted).toEqual([{ type: 'select', key: 'a' }]);

            // Unlocked, it offers them again. jsdom keeps the styles it computed for the hidden controls, so this reads hidden
            bridge.receive({ type: 'init', strings, readOnly: false });
            const shown = [...controls().querySelectorAll<HTMLElement>('[role="toolbar"], button')].filter((control) => !control.hidden && control.parentElement?.hidden !== true);
            expect(shown.filter((control) => control.getAttribute('role') === 'toolbar')).toHaveLength(1);
            expect(shown.filter((control) => control.getAttribute('aria-label') === 'Add block above')).toHaveLength(2);
        });

        it('should offer only the selected block\'s + when the pointer is over the selected block', () => {
            bridge.receive({ type: 'select', key: 'a' });
            fireEvent.mouseOver(document.querySelectorAll('h2')[0]!);

            expect(within(controls()).getAllByRole('button', { name: 'Add block above' })).toHaveLength(1);
        });

        it('should patch a block with its new HTML and dispatch a bubbling cms:render from its root element', () => {
            const rendered: Array<{ target: EventTarget | null; detail: unknown }> = [];
            document.addEventListener('cms:render', (event) => rendered.push({ target: event.target, detail: (event as CustomEvent).detail }));
            const first = document.querySelector('h2');

            bridge.receive({ type: 'patch', blocks: [{ key: 'a', html: marked('a', '<h2 class="cms-heading">First, changed</h2>') }] });

            expect(document.querySelector('h2')).toBe(first);
            expect(first).toHaveTextContent('First, changed');
            expect(rendered).toEqual([{ target: first, detail: { key: 'a', type: 'heading' } }]);
        });

        it('should tell the builder when a block it patches is not in the page', () => {
            bridge.receive({ type: 'patch', blocks: [{ key: 'gone', html: marked('gone', '<p>Gone</p>') }] });

            expect(posted).toEqual([{ type: 'missing', key: 'gone' }]);
        });

        it('should insert a new block after the nearest block before its spot that is in the page', () => {
            const rendered: unknown[] = [];
            document.addEventListener('cms:render', (event) => rendered.push((event as CustomEvent).detail));

            bridge.receive({ type: 'insert', block: { key: 'new', html: marked('new', '<h2 class="cms-heading">New</h2>') }, previous: ['hidden', 'a'], next: ['b'] });

            expect(Array.from(document.querySelectorAll('main > h2, main > section'), (element) => element.textContent)).toEqual(['First', 'New', 'Inside', 'Last']);
            expect(rendered).toEqual([{ key: 'new', type: 'heading' }]);
        });

        it('should insert a new block before the nearest block after its spot when none before it is in the page', () => {
            bridge.receive({ type: 'insert', block: { key: 'new', html: marked('new', '<h2 class="cms-heading">New</h2>') }, previous: [], next: ['hidden', 'd'] });

            expect(Array.from(document.querySelectorAll('main > h2, main > section'), (element) => element.textContent)).toEqual(['First', 'Inside', 'New', 'Last']);
        });

        it('should move a block that is already in the page to its new spot, such as one moved out of its parent', () => {
            bridge.receive({ type: 'insert', block: { key: 'c', html: marked('c', '<h2 class="cms-heading">Moved</h2>') }, previous: ['d'], next: [] });

            expect(Array.from(document.querySelectorAll('h2'), (element) => element.textContent)).toEqual(['First', 'Last', 'Moved']);
            expect(document.querySelector('section a')?.innerHTML).toBe('');
        });

        it('should tell the builder when it has nowhere to insert a block', () => {
            bridge.receive({ type: 'insert', block: { key: 'new', html: marked('new', '<h2>New</h2>') }, previous: [], next: [] });

            expect(posted).toEqual([{ type: 'missing', key: 'new' }]);
            expect(document.body).not.toHaveTextContent('New');
        });

        it('should remove a block with its markers', () => {
            bridge.receive({ type: 'remove', key: 'b' });

            expect(document.querySelector('main')?.innerHTML).toBe(`${marked('a', '<h2 class="cms-heading">First</h2>')}${marked('d', '<h2 class="cms-heading">Last</h2>')}`);
        });
    });

    it('should leave the page alone until the builder has set it up', () => {
        posted = [];
        const click = new MouseEvent('click', { bubbles: true, cancelable: true });

        document.querySelector('h2')?.dispatchEvent(click);

        expect(posted).toEqual([]);
        expect(click.defaultPrevented).toBe(false);
    });

    it('should stop listening once disposed', () => {
        bridge.receive({ type: 'init', strings });
        bridge.dispose();
        posted = [];

        document.querySelector('h2')?.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));

        expect(posted).toEqual([]);
        expect(document.querySelector('[data-cms-bridge]')).toBeNull();
    });
});
