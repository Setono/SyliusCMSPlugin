import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { FieldRow } from '../src/form/FieldRow';
import { createTranslator, TranslatorProvider } from '../src/i18n';
import type { FieldSchema, JsonValue } from '../src/types';
import { heading, strings } from './fixtures';

const [textSchema, choiceSchema] = heading.fields.map((field) => field.schema) as [FieldSchema, FieldSchema];

function renderField(schema: FieldSchema, value?: JsonValue, onChange = vi.fn()) {
    render(
        <TranslatorProvider value={createTranslator(strings)}>
            <FieldRow id="field" schema={schema} value={value} onChange={onChange} locale="en_US" />
        </TranslatorProvider>,
    );

    return onChange;
}

describe('rendering a form from a field schema', () => {
    it('should render a text field as a labelled textbox that reports what is typed', () => {
        const onChange = renderField(textSchema, 'Hi');

        const textbox = screen.getByRole('textbox', { name: 'Text' });
        expect(textbox).toHaveValue('Hi');
        expect(textbox).toHaveAttribute('aria-required', 'true');

        fireEvent.change(textbox, { target: { value: 'Hello' } });

        expect(onChange).toHaveBeenCalledWith('Hello');
    });

    it('should render an empty textbox for a field with no value', () => {
        renderField(textSchema);

        expect(screen.getByRole('textbox', { name: 'Text' })).toHaveValue('');
    });

    it('should limit the length of a text field when its options say so', () => {
        renderField({ ...textSchema, 'x-cms-options': { maxLength: 60 } });

        expect(screen.getByRole('textbox', { name: 'Text' })).toHaveAttribute('maxlength', '60');
    });

    it('should show the help text and describe the control with it', () => {
        renderField({ ...textSchema, 'x-cms-help': 'Shown as the page heading' });

        expect(screen.getByRole('textbox', { name: 'Text' })).toHaveAccessibleDescription('Shown as the page heading');
    });

    it('should render a choice field as a select with its choices and the default selected', () => {
        renderField(choiceSchema);

        const select = screen.getByRole('combobox', { name: 'Level' });
        expect(select).toHaveValue('h2');
        expect(screen.getAllByRole('option').map((option) => [option.getAttribute('value'), option.textContent])).toEqual([
            ['h1', 'Heading 1'],
            ['h2', 'Heading 2'],
            ['h3', 'Heading 3'],
            ['h4', 'Heading 4'],
        ]);
    });

    it('should select the stored choice and report a new one', () => {
        const onChange = renderField(choiceSchema, 'h3');

        const select = screen.getByRole('combobox', { name: 'Level' });
        expect(select).toHaveValue('h3');

        fireEvent.change(select, { target: { value: 'h1' } });

        expect(onChange).toHaveBeenCalledWith('h1');
    });

    it('should offer an empty choice when there is no default', () => {
        renderField({ ...choiceSchema, default: undefined });

        expect(screen.getByRole('combobox', { name: 'Level' })).toHaveValue('');
        expect(screen.getAllByRole('option')).toHaveLength(5);
    });

    it('should render a note for a field type the builder does not know', () => {
        renderField({ ...textSchema, 'x-cms-type': 'app_opening_hours', 'x-cms-label': 'Opening hours' });

        expect(screen.getByText('Opening hours')).toBeInTheDocument();
        expect(screen.getByText("This field type isn't supported yet")).toBeInTheDocument();
        expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
    });
});
