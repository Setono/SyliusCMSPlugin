import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { FieldRow } from '../src/form/FieldRow';
import { createTranslator, TranslatorProvider } from '../src/i18n';
import type { JsonObject, JsonValue } from '../src/types';
import { richTextSchema, strings } from './fixtures';

function doc(...content: JsonObject[]): JsonObject {
    return { type: 'doc', content };
}

function paragraph(...content: JsonObject[]): JsonObject {
    return content.length === 0 ? { type: 'paragraph' } : { type: 'paragraph', content };
}

function text(value: string, marks: string[] = []): JsonObject {
    return marks.length === 0 ? { type: 'text', text: value } : { type: 'text', text: value, marks: marks.map((type) => ({ type })) };
}

function renderRichText(value?: JsonValue, onChange = vi.fn()) {
    const field = (current: JsonValue | undefined) => (
        <TranslatorProvider value={createTranslator(strings)}>
            <FieldRow id="field" schema={richTextSchema} value={current} onChange={onChange} locale="en_US" />
        </TranslatorProvider>
    );
    const { rerender } = render(field(value));

    return { onChange, rerender: (next: JsonValue | undefined) => rerender(field(next)) };
}

/** Selects everything in the editor the way a keyboard does. */
function selectAll(editor: HTMLElement): void {
    fireEvent.keyDown(editor, { key: 'a', ctrlKey: true });
}

describe('the rich text field', () => {
    it('should show the text of the document it falls back to while it is empty, and copy the document here', () => {
        const fallback = doc(paragraph(text('Hello '), text('world', ['bold'])));
        const onChange = vi.fn();
        render(
            <TranslatorProvider value={createTranslator({ ...strings, 'setono_sylius_cms.builder.copy_it_here': 'Copy it here' })}>
                <FieldRow id="field" schema={richTextSchema} value={undefined} onChange={onChange} locale="da_DK" fallback={{ value: fallback, localeName: 'English' }} />
            </TranslatorProvider>,
        );

        expect(screen.getByRole('textbox', { name: 'Content' }).querySelector('p')).toHaveAttribute('data-placeholder', 'Hello world');

        fireEvent.click(screen.getByRole('button', { name: 'Copy it here' }));

        expect(onChange).toHaveBeenCalledExactlyOnceWith(fallback);
    });

    it('should show the stored document in a labelled, multi-line textbox', () => {
        renderRichText(doc(paragraph(text('Hello '), text('world', ['bold']))));

        const editor = screen.getByRole('textbox', { name: 'Content' });
        expect(editor).toHaveAttribute('aria-multiline', 'true');
        expect(editor.innerHTML).toContain('<p>Hello <strong>world</strong></p>');
    });

    it('should toggle bold on the selected text from its toolbar', () => {
        const { onChange } = renderRichText(doc(paragraph(text('Hello world'))));
        const editor = screen.getByRole('textbox', { name: 'Content' });
        const bold = screen.getByRole('toolbar', { name: 'Formatting' }).querySelector('button[aria-label="Bold"]');
        expect(bold).not.toBeNull();
        expect(bold).toHaveAttribute('aria-pressed', 'false');

        selectAll(editor);
        fireEvent.click(bold!);

        expect(onChange).toHaveBeenLastCalledWith(doc(paragraph(text('Hello world', ['bold']))));
        expect(bold).toHaveAttribute('aria-pressed', 'true');

        fireEvent.click(bold!);

        expect(onChange).toHaveBeenLastCalledWith(doc(paragraph(text('Hello world'))));
        expect(bold).toHaveAttribute('aria-pressed', 'false');
    });

    it('should report an emptied editor as no value', () => {
        const { onChange } = renderRichText(doc(paragraph(text('Hello'))));
        const editor = screen.getByRole('textbox', { name: 'Content' });

        selectAll(editor);
        fireEvent.keyDown(editor, { key: 'Backspace' });

        expect(onChange).toHaveBeenLastCalledWith(null);
    });

    it('should follow a value changed elsewhere, such as in another locale, without reporting it as an edit', () => {
        const { onChange, rerender } = renderRichText(doc(paragraph(text('Hello'))));

        rerender(doc(paragraph(text('Hej'))));
        expect(screen.getByRole('textbox', { name: 'Content' }).textContent).toBe('Hej');

        rerender(undefined);
        expect(screen.getByRole('textbox', { name: 'Content' }).textContent).toBe('');

        expect(onChange).not.toHaveBeenCalled();
    });
});
