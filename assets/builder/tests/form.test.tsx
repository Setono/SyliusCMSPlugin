import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { fromLocalInput, toLocalInput } from '../src/dates';
import { FieldRow } from '../src/form/FieldRow';
import { createTranslator, TranslatorProvider } from '../src/i18n';
import type { FieldSchema, JsonValue } from '../src/types';
import { group, heading, strings } from './fixtures';

const [textSchema, choiceSchema] = heading.fields.map((field) => field.schema) as [FieldSchema, FieldSchema];
const [toggleSchema] = group.fields.map((field) => field.schema) as [FieldSchema];

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

    it('should render a textarea field as a labelled, multi-line textbox that keeps newlines', () => {
        const textareaSchema: FieldSchema = { ...textSchema, 'x-cms-type': 'textarea', 'x-cms-label': 'Excerpt', 'x-cms-options': { maxLength: 160 } };
        const onChange = renderField(textareaSchema, 'One\nTwo');

        const textbox = screen.getByRole('textbox', { name: 'Excerpt' });
        expect(textbox.tagName).toBe('TEXTAREA');
        expect(textbox).toHaveValue('One\nTwo');
        expect(textbox).toHaveAttribute('maxlength', '160');
        expect(textbox).toHaveAttribute('aria-required', 'true');

        fireEvent.change(textbox, { target: { value: 'One\nTwo\nThree' } });

        expect(onChange).toHaveBeenCalledWith('One\nTwo\nThree');
    });

    it('should render a code field as a multi-line textbox that is not spell checked and reports the code as it is typed', () => {
        const codeSchema: FieldSchema = { type: 'string', 'x-cms-type': 'code', 'x-cms-label': 'Code', 'x-cms-help': null, 'x-cms-required': false, 'x-cms-translatable': false };
        const code = '<div id="reviews"></div>\n<script src="https://widgets.example.com/reviews.js" async></script>';
        const onChange = renderField(codeSchema, code);

        const textbox = screen.getByRole('textbox', { name: 'Code' });
        expect(textbox.tagName).toBe('TEXTAREA');
        expect(textbox).toHaveValue(code);
        expect(textbox).toHaveAttribute('spellcheck', 'false');
        expect(textbox).toHaveAttribute('autocapitalize', 'off');
        expect(textbox).toHaveAttribute('dir', 'ltr');

        fireEvent.change(textbox, { target: { value: `${code}\n<p>  Spaces kept  </p>` } });

        expect(onChange).toHaveBeenCalledWith(`${code}\n<p>  Spaces kept  </p>`);
    });

    it('should render an empty code field as an empty textbox', () => {
        renderField({ type: 'string', 'x-cms-type': 'code', 'x-cms-label': 'Code', 'x-cms-help': null, 'x-cms-required': false, 'x-cms-translatable': false });

        expect(screen.getByRole('textbox', { name: 'Code' })).toHaveValue('');
    });

    it('should render a note for a field type the builder does not know', () => {
        renderField({ ...textSchema, 'x-cms-type': 'app_opening_hours', 'x-cms-label': 'Opening hours' });

        expect(screen.getByText('Opening hours')).toBeInTheDocument();
        expect(screen.getByText("This field type isn't supported yet")).toBeInTheDocument();
        expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
    });

    it('should render a toggle field as a labelled checkbox that is off by default and reports on and off', () => {
        const onChange = renderField(toggleSchema);

        const checkbox = screen.getByRole('checkbox', { name: 'Full width' });
        expect(checkbox).not.toBeChecked();

        fireEvent.click(checkbox);
        expect(onChange).toHaveBeenLastCalledWith(true);
    });

    it('should show a toggle that is on as checked and report turning it off', () => {
        const onChange = renderField(toggleSchema, true);

        const checkbox = screen.getByRole('checkbox', { name: 'Full width' });
        expect(checkbox).toBeChecked();

        fireEvent.click(checkbox);
        expect(onChange).toHaveBeenLastCalledWith(false);
    });
});

describe('date fields', () => {
    const dateSchema: FieldSchema = { type: 'string', format: 'date', 'x-cms-type': 'date', 'x-cms-label': 'Day', 'x-cms-help': null, 'x-cms-required': false, 'x-cms-translatable': false };
    const dateTimeSchema: FieldSchema = { ...dateSchema, format: 'date-time', 'x-cms-type': 'date_time', 'x-cms-label': 'At' };

    it('should render a date field as a date input that reports the day as it is, and nothing for an empty one', () => {
        const onChange = renderField(dateSchema, '2026-11-13');

        const input = screen.getByLabelText('Day');
        expect(input).toHaveAttribute('type', 'date');
        expect(input).toHaveValue('2026-11-13');

        fireEvent.change(input, { target: { value: '2026-11-27' } });
        expect(onChange).toHaveBeenLastCalledWith('2026-11-27');

        fireEvent.change(input, { target: { value: '' } });
        expect(onChange).toHaveBeenLastCalledWith('');
    });

    it("should render a date and time field in the editor's time zone and report it in UTC once it's whole", () => {
        const onChange = renderField(dateTimeSchema, '2026-11-13T09:30:00Z');

        const input = screen.getByLabelText('At');
        expect(input).toHaveAttribute('type', 'datetime-local');
        expect(input).toHaveValue(toLocalInput('2026-11-13T09:30:00Z'));

        fireEvent.change(input, { target: { value: '2026-11-27T00:00' } });
        expect(onChange).toHaveBeenLastCalledWith(fromLocalInput('2026-11-27T00:00'));

        fireEvent.change(input, { target: { value: '' } });
        expect(onChange).toHaveBeenLastCalledWith(null);
        expect(onChange).toHaveBeenCalledTimes(2);
    });

    it('should show an empty date and time input for a value that is no time', () => {
        renderField(dateTimeSchema, 'next friday');

        expect(screen.getByLabelText('At')).toHaveValue('');
    });
});

describe('number fields', () => {
    const intervalSchema: FieldSchema = {
        type: 'integer',
        minimum: 2,
        maximum: 30,
        default: 5,
        'x-cms-type': 'number',
        'x-cms-label': 'Interval',
        'x-cms-help': null,
        'x-cms-required': false,
        'x-cms-translatable': false,
        'x-cms-options': { min: 2, max: 30, step: null, integer: true },
    };
    const ratingSchema: FieldSchema = {
        ...intervalSchema,
        type: 'number',
        minimum: undefined,
        maximum: undefined,
        default: undefined,
        'x-cms-label': 'Rating',
        'x-cms-options': { min: null, max: null, step: 0.5, integer: false },
    };

    it('should render a number field as a spin button within its bounds that reports the number typed', () => {
        const onChange = renderField(intervalSchema, 8);

        const input = screen.getByRole('spinbutton', { name: 'Interval' });
        expect(input).toHaveValue(8);
        expect(input).toHaveAttribute('min', '2');
        expect(input).toHaveAttribute('max', '30');
        // A field that takes whole numbers moves in whole steps
        expect(input).toHaveAttribute('step', '1');
        expect(input).toHaveAttribute('inputmode', 'numeric');

        fireEvent.change(input, { target: { value: '12' } });
        expect(onChange).toHaveBeenLastCalledWith(12);
    });

    it('should show the default while the field is empty, and report clearing it as no value', () => {
        const onChange = renderField(intervalSchema, 8);

        fireEvent.change(screen.getByRole('spinbutton', { name: 'Interval' }), { target: { value: '' } });
        expect(onChange).toHaveBeenLastCalledWith(null);

        cleanup();
        renderField(intervalSchema);
        const empty = screen.getByRole('spinbutton', { name: 'Interval' });
        expect(empty).toHaveValue(null);
        expect(empty).toHaveAttribute('placeholder', '5');
    });

    it('should take fractions in the steps of the field, or of any size without a step', () => {
        const onChange = renderField(ratingSchema, 4.5);

        const input = screen.getByRole('spinbutton', { name: 'Rating' });
        expect(input).toHaveValue(4.5);
        expect(input).toHaveAttribute('step', '0.5');
        expect(input).not.toHaveAttribute('min');
        expect(input).toHaveAttribute('inputmode', 'decimal');

        fireEvent.change(input, { target: { value: '3.5' } });
        expect(onChange).toHaveBeenLastCalledWith(3.5);

        cleanup();
        renderField({ ...ratingSchema, 'x-cms-options': { min: null, max: null, step: null, integer: false } });
        expect(screen.getByRole('spinbutton', { name: 'Rating' })).toHaveAttribute('step', 'any');
    });
});
