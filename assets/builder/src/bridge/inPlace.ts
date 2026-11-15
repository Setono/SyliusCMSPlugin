/**
 * Typing in place: makes a field that cms_field() marked editable right where it is in the preview's page, and reports
 * each change of its value. Text and textareas are typed as plain text, and rich text in a Tiptap editor with the one
 * rich text schema the right pane uses. See "Working in the preview" in docs/spec/builder.md
 */
import { Editor } from '@tiptap/core';
import { UndoRedo } from '@tiptap/extensions';
import { documentValue, isEmptyDocument, richTextExtensions } from '../richText/schema';
import type { JsonValue } from '../types';
import { fieldNodes, findFields, type FieldRange } from './markers';
import { parseHtml } from './morph';

/** A spot in the page's text, such as where the editor clicked. */
export interface Caret {
    node: Node;
    offset: number;
}

export interface TypingInPlace {
    readonly field: FieldRange;
    /** The element the editor types in. */
    readonly host: HTMLElement;
    /** The editor of rich text, or null for text and textareas. */
    readonly editor: Editor | null;
    /** Ends the typing and leaves the field's output as the shop renders it, between its markers. */
    stop(): void;
}

export interface TypingOptions {
    /** Where the editor clicked: the spot in the text, and the point in the page's viewport. */
    caret: Caret | null;
    at: { x: number; y: number } | null;
    /** Called with each new value: a string, or a document for rich text. null means it's empty. */
    onChange(value: JsonValue | null): void;
    /** Called once when the typing ends, whether the editor or stop() ended it. */
    onEnd(): void;
}

/** The spot in the page's text at a point in its viewport, where the browser can tell. */
export function caretAt(document: Document, x: number, y: number): Caret | null {
    const position = document.caretPositionFromPoint?.(x, y);
    if (position !== null && position !== undefined) {
        return { node: position.offsetNode, offset: position.offset };
    }

    const range = document.caretRangeFromPoint?.(x, y);

    return range === null || range === undefined ? null : { node: range.startContainer, offset: range.startOffset };
}

/**
 * The marked field at the spot the editor clicked, or else at the element clicked: the innermost field whose output
 * holds it, or a field that is all the clicked element holds. Null when the editor didn't click a field.
 */
export function fieldAt(document: Document, target: Node, caret: Caret | null): FieldRange | null {
    const node = caret?.node ?? target;
    const fields = findFields(document);
    const holding = fields.filter((field) => holds(field, node));

    return holding.at(-1) ?? fields.find((field) => field.start.parentNode === node && isAllOf(field, node)) ?? null;
}

export function startTyping(field: FieldRange, options: TypingOptions): TypingInPlace {
    const { host, restore } = hostOf(field);
    const document = host.ownerDocument;
    const typing = field.type === 'rich_text' ? typeRichText(host, options) : typePlainText(host, field.type === 'textarea', options);
    let stopped = false;

    const stop = (): void => {
        if (stopped) {
            return;
        }

        stopped = true;
        host.removeEventListener('keydown', onKeyDown);
        host.removeEventListener('blur', stop);
        const output = typing.end();
        if (document.activeElement === host) {
            host.blur();
        }
        restore(output);
        options.onEnd();
    };

    const onKeyDown = (event: KeyboardEvent): void => {
        // Enter ends a text field, which has one line, and Escape ends any field
        if (event.key === 'Escape' || (event.key === 'Enter' && field.type === 'text' && !event.isComposing)) {
            event.preventDefault();
            stop();
        }
    };

    host.addEventListener('keydown', onKeyDown);
    host.addEventListener('blur', stop);

    return { field, host, editor: typing.editor, stop };
}

interface Typing {
    editor: Editor | null;
    /** Stops the editing and answers the nodes of the field's output for its value. */
    end(): Node[];
}

function typePlainText(host: HTMLElement, multiline: boolean, { caret, onChange }: TypingOptions): Typing {
    const document = host.ownerDocument;
    let value = textOf(host, multiline);
    const onInput = (): void => {
        const typed = textOf(host, multiline);
        if (typed !== value) {
            value = typed;
            onChange(typed);
        }
    };

    // In a summary or a button, Space activates it, such as opening an Accordion item, so the space is typed here
    const activatedBySpace = host.closest('summary, button') !== null;
    const onSpace = (event: KeyboardEvent): void => {
        if (event.key !== ' ' || !activatedBySpace) {
            return;
        }

        event.preventDefault();
        if (event.type === 'keydown') {
            insertText(host, ' ');
        }
    };

    host.setAttribute('contenteditable', 'plaintext-only');
    // Keeps the spaces and newlines as typed, rather than as non-breaking spaces and line break elements
    host.style.whiteSpace = 'pre-wrap';
    host.addEventListener('input', onInput);
    host.addEventListener('keydown', onSpace);
    host.addEventListener('keyup', onSpace);
    host.focus({ preventScroll: true });
    placeCaret(host, caret);

    return {
        editor: null,
        end() {
            host.removeEventListener('input', onInput);
            host.removeEventListener('keydown', onSpace);
            host.removeEventListener('keyup', onSpace);

            return value === '' ? [] : [document.createTextNode(value)];
        },
    };
}

function typeRichText(host: HTMLElement, { at, onChange }: TypingOptions): Typing {
    const content = host.innerHTML;
    host.replaceChildren();

    const editor = new Editor({
        element: { mount: host },
        extensions: [...richTextExtensions, UndoRedo],
        content,
        editorProps: { attributes: { 'aria-multiline': 'true' } },
        onUpdate: ({ editor: updated }) => onChange(documentValue(updated)),
    });

    let position: number | null = null;
    if (at !== null) {
        try {
            position = editor.view.posAtCoords({ left: at.x, top: at.y })?.pos ?? null;
        } catch {
            // Without a layout, such as outside a browser, there's no position at the point
        }
    }
    editor.commands.focus(position ?? 'end', { scrollIntoView: false });

    return {
        editor,
        end() {
            const html = isEmptyDocument(editor) ? '' : editor.getHTML();
            editor.destroy();

            return parseHtml(host.ownerDocument, html);
        },
    };
}

/**
 * The element to type in: the field's own element when the field is all it holds, so the page looks the same while
 * the editor types, or else a wrapper around the field's output. The markers are kept out of the way meanwhile, and
 * restore() puts the field's output back between them.
 */
function hostOf(field: FieldRange): { host: HTMLElement; restore(output: Node[]): void } {
    const parent = field.start.parentElement as HTMLElement | null;
    if (parent !== null && isAllOf(field, parent)) {
        const restoreAttributes = keepAttributes(parent);
        field.start.remove();
        field.end.remove();

        return {
            host: parent,
            restore(output) {
                restoreAttributes();
                parent.replaceChildren(field.start, ...output, field.end);
            },
        };
    }

    const wrapper = field.start.ownerDocument.createElement(field.type === 'rich_text' ? 'div' : 'span');
    wrapper.append(...fieldNodes(field));
    field.start.after(wrapper);

    return {
        host: wrapper,
        restore(output) {
            wrapper.replaceWith(...output);
        },
    };
}

function placeCaret(host: HTMLElement, caret: Caret | null): void {
    const selection = host.ownerDocument.getSelection();
    if (selection === null) {
        return;
    }

    try {
        if (caret !== null && host.contains(caret.node)) {
            selection.collapse(caret.node, caret.offset);

            return;
        }
    } catch {
        // The spot isn't in the text any more, such as an offset past the markers taken out
    }

    selection.selectAllChildren(host);
    selection.collapseToEnd();
}

/** Types text at the caret, as the browser does, with its input event. */
function insertText(host: HTMLElement, text: string): void {
    const document = host.ownerDocument;
    // Deprecated, but the one way to type that keeps the browser's own undo. Where it's missing, the text goes in by hand
    if (typeof document.execCommand === 'function' && document.execCommand('insertText', false, text)) {
        return;
    }

    const range = document.getSelection()?.rangeCount === 1 ? document.getSelection()?.getRangeAt(0) : undefined;
    if (range === undefined || !host.contains(range.commonAncestorContainer)) {
        return;
    }

    const node = document.createTextNode(text);
    range.deleteContents();
    range.insertNode(node);
    range.setStartAfter(node);
    range.collapse(true);
    host.dispatchEvent(new (document.defaultView?.InputEvent ?? InputEvent)('input', { bubbles: true, inputType: 'insertText', data: text }));
}

/** The plain text typed in an element: line breaks are newlines, except the one a browser keeps at the end. */
function textOf(element: HTMLElement, multiline: boolean): string {
    const parts: string[] = [];
    const read = (node: Node): void => {
        node.childNodes.forEach((child) => {
            if (child.nodeType === child.TEXT_NODE) {
                parts.push((child as Text).data);
            } else if (child.nodeName === 'BR') {
                parts.push('\n');
            } else if (child.nodeType === child.ELEMENT_NODE) {
                read(child);
            }
        });
    };
    read(element);
    if (element.lastChild?.nodeName === 'BR') {
        parts.pop();
    }

    const text = parts.join('');

    return multiline ? text : text.replace(/\r?\n/g, ' ');
}

/** Whether the node is part of the field's output. */
function holds(field: FieldRange, node: Node): boolean {
    return (field.start.compareDocumentPosition(node) & node.DOCUMENT_POSITION_FOLLOWING) !== 0 && (field.end.compareDocumentPosition(node) & node.DOCUMENT_POSITION_PRECEDING) !== 0;
}

/** Whether the field, with its markers, is everything the element holds. */
function isAllOf(field: FieldRange, element: Node): boolean {
    return Array.from(element.childNodes).every((child) => child === field.start || child === field.end || holds(field, child));
}

/** Keeps the element's attributes, and answers what puts them back as they were. */
function keepAttributes(element: Element): () => void {
    const kept = Array.from(element.attributes, ({ name, value }) => [name, value] as const);

    return () => {
        Array.from(element.attributes).forEach(({ name }) => element.removeAttribute(name));
        kept.forEach(([name, value]) => element.setAttribute(name, value));
    };
}
