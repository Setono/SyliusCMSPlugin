import { fireEvent, within } from '@testing-library/dom';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { createBridge, type Bridge } from '../../src/bridge/bridge';
import type { BridgeStrings, FromBridge } from '../../src/bridge/protocol';
import type { JsonObject } from '../../src/types';

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

function block(key: string, type: string, html: string): string {
    return `<!--cms:block ${key} ${type}-->${html}<!--/cms:block ${key}-->`;
}

function field(key: string, name: string, type: string, html: string): string {
    return `<!--cms:field ${key} ${name} ${type}-->${html}<!--/cms:field ${key} ${name}-->`;
}

const HEADING = `<h2 class="cms-heading">${field('a', 'text', 'text', 'Hello')}</h2>`;

const PAGE = [
    block('a', 'heading', HEADING),
    block('q', 'quote', `<blockquote><p>${field('q', 'quote', 'textarea', 'First line\nSecond line')}</p><cite>Ann</cite></blockquote>`),
    block('r', 'rich_text', `<div class="cms-rich-text">${field('r', 'content', 'rich_text', '<p>Hello <strong>bold</strong> world</p>')}</div>`),
    block('n', 'note', `<p class="cms-note">Note: ${field('n', 'label', 'text', 'cheap')} today</p>`),
    block('p', 'plain', '<p class="cms-plain">Printed without cms_field()</p>'),
].join('');

function query<T extends Element = HTMLElement>(selector: string): T {
    const element = document.querySelector<T>(selector);
    if (element === null) {
        throw new Error(`Nothing in the page matches ${selector}`);
    }

    return element;
}

/** The bridge's own controls, which it draws in a shadow root so the shop's CSS leaves them alone. */
function controls(): HTMLElement {
    const root = document.querySelector('[data-cms-bridge]')?.shadowRoot?.firstElementChild;
    if (!(root instanceof HTMLElement)) {
        throw new Error('The bridge has drawn no controls');
    }

    return root;
}

/** Types into an element in place: what the browser does to its content, and the input event it fires. */
function type(element: HTMLElement, html: string): void {
    element.innerHTML = html;
    element.dispatchEvent(new InputEvent('input', { bubbles: true }));
}

function doc(...content: JsonObject[]): JsonObject {
    return { type: 'doc', content: [{ type: 'paragraph', content }] };
}

function text(value: string, marks: string[] = []): JsonObject {
    return marks.length === 0 ? { type: 'text', text: value } : { type: 'text', text: value, marks: marks.map((mark) => ({ type: mark })) };
}

describe('typing in place in the preview', () => {
    let posted: FromBridge[];
    let bridge: Bridge;

    const edits = () => posted.filter((message) => message.type === 'edit');

    beforeEach(() => {
        document.body.innerHTML = `<main>${PAGE}</main>`;
        Element.prototype.scrollIntoView = () => undefined;
        posted = [];
        bridge = createBridge(document, (message) => posted.push(message));
        bridge.receive({ type: 'init', strings });
        posted = [];
    });

    afterEach(() => {
        bridge.dispose();
        delete (document as Partial<Document>).caretPositionFromPoint;
    });

    it('should make a marked text field editable where it is clicked, select its block and focus it', () => {
        const heading = query('h2');

        fireEvent.click(heading);

        expect(posted).toEqual([{ type: 'select', key: 'a' }]);
        expect(heading).toHaveAttribute('contenteditable', 'plaintext-only');
        expect(heading).toHaveFocus();
    });

    it('should only select the block of a field clicked while the working copy is read-only, and stop typing when it becomes so', () => {
        const heading = query('h2');
        fireEvent.click(heading);
        expect(heading).toHaveAttribute('contenteditable', 'plaintext-only');

        bridge.receive({ type: 'init', strings, readOnly: true });
        expect(heading).not.toHaveAttribute('contenteditable');

        posted = [];
        fireEvent.click(heading);

        expect(posted).toEqual([{ type: 'select', key: 'a' }]);
        expect(heading).not.toHaveAttribute('contenteditable');
    });

    it('should send the builder the text as the editor types it', () => {
        const heading = query('h2');
        fireEvent.click(heading);

        type(heading, 'Hello world');

        expect(edits()).toEqual([{ type: 'edit', key: 'a', field: 'text', value: 'Hello world' }]);
    });

    it('should keep a text field on one line and end typing in it on Enter, putting its markers back', () => {
        const heading = query('h2');
        fireEvent.click(heading);
        type(heading, 'Hello world');

        const enter = fireEvent.keyDown(heading, { key: 'Enter' });

        expect(enter).toBe(false);
        expect(heading).not.toHaveAttribute('contenteditable');
        expect(heading).not.toHaveFocus();
        expect(heading.outerHTML).toBe(`<h2 class="cms-heading">${field('a', 'text', 'text', 'Hello world')}</h2>`);
    });

    it('should end typing in place when the field loses focus, and leave the field as the shop renders it', () => {
        const heading = query('h2');
        fireEvent.click(heading);

        heading.blur();

        expect(heading.outerHTML).toBe(HEADING);
        expect(edits()).toEqual([]);
    });

    it('should end typing in place on Escape', () => {
        const heading = query('h2');
        fireEvent.click(heading);

        fireEvent.keyDown(heading, { key: 'Escape' });

        expect(heading).not.toHaveAttribute('contenteditable');
    });

    it('should leave clicks in the field being typed in to the caret', () => {
        const heading = query('h2');
        fireEvent.click(heading);
        posted = [];

        fireEvent.click(heading);

        expect(posted).toEqual([]);
        expect(heading).toHaveFocus();
    });

    it('should hold back a patch of the block being typed in until the typing ends, so the caret stays put', () => {
        const heading = query('h2');
        fireEvent.click(heading);
        type(heading, 'Hello world');

        bridge.receive({ type: 'patch', blocks: [{ key: 'a', html: block('a', 'heading', `<h2 class="cms-heading is-new">${field('a', 'text', 'text', 'Hello world')}</h2>`) }] });

        expect(heading).toHaveAttribute('contenteditable', 'plaintext-only');
        expect(heading).not.toHaveClass('is-new');

        heading.blur();

        expect(heading.outerHTML).toBe(`<h2 class="cms-heading is-new">${field('a', 'text', 'text', 'Hello world')}</h2>`);
    });

    it('should patch the other blocks while a field is typed in', () => {
        fireEvent.click(query('h2'));

        bridge.receive({ type: 'patch', blocks: [{ key: 'p', html: block('p', 'plain', '<p class="cms-plain">Changed</p>') }] });

        expect(query('.cms-plain')).toHaveTextContent('Changed');
        expect(query('h2')).toHaveFocus();
    });

    it('should end typing in a block the builder removes', () => {
        fireEvent.click(query('h2'));

        bridge.receive({ type: 'remove', key: 'a' });

        expect(document.querySelector('h2')).toBeNull();
        expect(document.body.innerHTML).not.toContain('cms:field a');
    });

    it("should end typing in place on undo, which the builder does, so the preview shows the field as undone", () => {
        const heading = query('h2');
        fireEvent.click(heading);
        type(heading, 'Hello world');

        const undo = fireEvent.keyDown(heading, { key: 'z', ctrlKey: true });
        bridge.receive({ type: 'patch', blocks: [{ key: 'a', html: block('a', 'heading', HEADING) }] });

        expect(undo).toBe(false);
        expect(posted.at(-1)).toEqual({ type: 'undo' });
        expect(heading).not.toHaveAttribute('contenteditable');
        expect(heading).toHaveTextContent(/^Hello$/);
    });

    it('should keep the newlines of a textarea', () => {
        const quote = query('blockquote p');
        fireEvent.click(quote);
        expect(quote).toHaveStyle({ whiteSpace: 'pre-wrap' });

        type(quote, 'First line\nSecond line<br>Third line<br>');

        expect(edits()).toEqual([{ type: 'edit', key: 'q', field: 'quote', value: 'First line\nSecond line\nThird line' }]);
    });

    it('should type a field that shares its element with other content in a wrapper of its own, where the click landed', () => {
        const note = query('.cms-note');
        const cheap = Array.from(note.childNodes).find((node) => node.textContent === 'cheap') as Text;
        document.caretPositionFromPoint = () => ({ offsetNode: cheap, offset: 2, getClientRect: () => null }) as unknown as CaretPosition;

        fireEvent.click(note);
        const wrapper = query('.cms-note [contenteditable]');
        type(wrapper, 'cheaper');

        expect(posted).toEqual([
            { type: 'select', key: 'n' },
            { type: 'edit', key: 'n', field: 'label', value: 'cheaper' },
        ]);
        expect(wrapper.tagName).toBe('SPAN');
        expect(note).not.toHaveAttribute('contenteditable');

        wrapper.blur();

        expect(note.innerHTML).toBe(`Note: ${field('n', 'label', 'text', 'cheaper')} today`);
    });

    it('should type a space in a summary, which would otherwise toggle its details', () => {
        document.querySelector('main')?.insertAdjacentHTML('beforeend', block('i', 'accordion_item', `<details><summary>${field('i', 'title', 'text', 'Shipping')}</summary></details>`));
        const summary = query('summary');
        fireEvent.click(summary);

        const keyDown = fireEvent.keyDown(summary, { key: ' ' });
        const keyUp = fireEvent.keyUp(summary, { key: ' ' });

        expect([keyDown, keyUp]).toEqual([false, false]);
        expect(edits()).toEqual([{ type: 'edit', key: 'i', field: 'title', value: 'Shipping ' }]);
        expect(query('details')).not.toHaveAttribute('open');
    });

    it('should only select a block when the click is not on a field typed in place', () => {
        fireEvent.click(query('.cms-plain'));
        fireEvent.click(query('.cms-note'));

        expect(posted).toEqual([
            { type: 'select', key: 'p' },
            { type: 'select', key: 'n' },
        ]);
        expect(document.querySelector('[contenteditable]')).toBeNull();
    });

    describe('rich text', () => {
        it('should edit rich text with the shared schema, its marks toggled from a toolbar in the preview', () => {
            const richText = query('.cms-rich-text');
            fireEvent.click(query('.cms-rich-text p'));

            expect(richText).toHaveAttribute('contenteditable', 'true');
            expect(richText.innerHTML).toBe('<p>Hello <strong>bold</strong> world</p>');
            const toolbar = within(controls()).getByRole('toolbar', { name: 'Formatting' });

            fireEvent.keyDown(richText, { key: 'a', ctrlKey: true });
            fireEvent.click(within(toolbar).getByRole('button', { name: 'Bold' }));

            expect(edits()).toEqual([{ type: 'edit', key: 'r', field: 'content', value: doc(text('Hello bold world', ['bold'])) }]);
            expect(within(toolbar).getByRole('button', { name: 'Bold' })).toHaveAttribute('aria-pressed', 'true');
        });

        it("should undo in the rich text editor first, as the right pane's does, and leave the builder's undo for when it has nothing left", () => {
            const richText = query('.cms-rich-text');
            fireEvent.click(query('.cms-rich-text p'));
            fireEvent.keyDown(richText, { key: 'a', ctrlKey: true });
            fireEvent.click(within(controls()).getByRole('button', { name: 'Bold' }));

            fireEvent.keyDown(richText, { key: 'z', ctrlKey: true });

            expect(edits().at(-1)).toEqual({ type: 'edit', key: 'r', field: 'content', value: doc(text('Hello '), text('bold', ['bold']), text(' world')) });
            expect(posted.some((message) => message.type === 'undo')).toBe(false);
            expect(richText).toHaveAttribute('contenteditable', 'true');

            fireEvent.keyDown(richText, { key: 'z', ctrlKey: true });

            expect(posted.at(-1)).toEqual({ type: 'undo' });
        });

        it('should keep the links the preview renders, whether they resolve or not, as the editor types', () => {
            const richText = query('.cms-rich-text');
            query('.cms-rich-text p').innerHTML =
                'Read <a href="http://localhost/en_US/story" data-cms-link-target="entry" data-cms-link-ref="0193a5be-0000-7000-8000-000000000001">our story</a> ' +
                'or meet <span data-cms-link-target="entry" data-cms-link-ref="0193a5be-0000-7000-8000-000000000002" data-cms-link-anchor="team">the team</span>';
            fireEvent.click(query('.cms-rich-text p'));

            fireEvent.keyDown(richText, { key: 'a', ctrlKey: true });
            fireEvent.click(within(controls()).getByRole('button', { name: 'Bold' }));

            const story = { type: 'cmsLink', attrs: { target: 'entry', ref: '0193a5be-0000-7000-8000-000000000001', anchor: null } };
            const team = { type: 'cmsLink', attrs: { target: 'entry', ref: '0193a5be-0000-7000-8000-000000000002', anchor: 'team' } };
            expect(edits()).toEqual([
                {
                    type: 'edit',
                    key: 'r',
                    field: 'content',
                    value: doc(
                        text('Read ', ['bold']),
                        { type: 'text', text: 'our story', marks: [{ type: 'bold' }, story] },
                        text(' or meet ', ['bold']),
                        { type: 'text', text: 'the team', marks: [{ type: 'bold' }, team] },
                    ),
                },
            ]);
        });

        it('should send no value for rich text emptied in place', () => {
            const richText = query('.cms-rich-text');
            fireEvent.click(query('.cms-rich-text p'));

            fireEvent.keyDown(richText, { key: 'a', ctrlKey: true });
            fireEvent.keyDown(richText, { key: 'Backspace' });

            expect(edits()).toEqual([{ type: 'edit', key: 'r', field: 'content', value: null }]);
        });

        it('should put the rich text back as HTML between its markers when the typing ends, and drop the toolbar', () => {
            const richText = query('.cms-rich-text');
            fireEvent.click(query('.cms-rich-text p'));
            fireEvent.keyDown(richText, { key: 'a', ctrlKey: true });
            fireEvent.click(within(controls()).getByRole('button', { name: 'Italic' }));

            fireEvent.keyDown(richText, { key: 'Escape' });

            expect(richText.outerHTML).toBe(`<div class="cms-rich-text">${field('r', 'content', 'rich_text', '<p><em>Hello </em><strong><em>bold</em></strong><em> world</em></p>')}</div>`);
            expect(within(controls()).queryByRole('toolbar', { name: 'Formatting' })).toBeNull();
        });
    });
});
