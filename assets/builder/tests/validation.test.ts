import { describe, expect, it } from 'vitest';
import type { ValidationError } from '../src/types';
import {
    blockHasErrors,
    blockMessages,
    conditionFieldMessages,
    conditionMessages,
    entryFieldMessages,
    entryMessages,
    fieldMessages,
    firstBlockWithError,
    layoutFieldMessages,
    placeOf,
} from '../src/validation';
import { block, blockOf } from './fixtures';

const errors: ValidationError[] = [
    { block: 'heading', field: 'text', locale: 'en_US', message: 'This field is required.' },
    { block: 'heading', field: 'text', locale: 'da_DK', message: 'This field is required.' },
    { block: 'heading', field: 'level', locale: null, message: 'The value you selected is not a valid choice.' },
    { block: 'columns', field: null, locale: null, message: 'Needs at least 2 Column blocks inside.' },
    { block: null, field: null, locale: null, message: 'Needs at least one Heading block inside.' },
    { block: null, field: 'campaign', locale: null, message: 'This value should not be blank.' },
];

describe('mapping validation errors onto tree rows', () => {
    it('should flag a block with an error of its own or in one of its fields', () => {
        expect(blockHasErrors(errors, 'heading')).toBe(true);
        expect(blockHasErrors(errors, 'columns')).toBe(true);
        expect(blockHasErrors(errors, 'column')).toBe(false);
    });

    it('should find the first block with an error in tree order, depth first', () => {
        const tree = [block('a'), blockOf('columns', 'b', [blockOf('column', 'c', [block('d')])]), block('e')];
        const found = (keys: string[]): string | null =>
            firstBlockWithError(
                tree,
                keys.map((key) => ({ block: key, field: null, locale: null, message: 'Wrong' })),
            );

        expect(found(['e', 'd'])).toBe('d');
        expect(found(['e', 'b', 'd'])).toBe('b');
        expect(found(['e'])).toBe('e');
    });

    it('should find no block when only the entry, or blocks the tree no longer has, have errors', () => {
        expect(firstBlockWithError([block('a')], errors)).toBeNull();
        expect(firstBlockWithError([block('a')], [])).toBeNull();
    });
});

describe('mapping validation errors onto fields', () => {
    it('should give a field its messages, saying which locale those of another locale are in', () => {
        expect(fieldMessages(errors, 'heading', 'text', 'en_US')).toEqual(['This field is required.', 'This field is required. (da_DK)']);
        expect(fieldMessages(errors, 'heading', 'level', 'en_US')).toEqual(['The value you selected is not a valid choice.']);
        expect(fieldMessages(errors, 'heading', 'missing', 'en_US')).toEqual([]);
        expect(fieldMessages(errors, 'columns', 'text', 'en_US')).toEqual([]);
    });

    it('should give a block the messages that are about the block as a whole', () => {
        expect(blockMessages(errors, 'columns')).toEqual(['Needs at least 2 Column blocks inside.']);
        expect(blockMessages(errors, 'heading')).toEqual([]);
    });

    it('should give the entry its own messages, naming the entry field each is in', () => {
        expect(entryMessages(errors, 'en_US')).toEqual(['Needs at least one Heading block inside.', 'campaign: This value should not be blank.']);
        expect(entryMessages([{ block: null, field: 'teaser', locale: 'da_DK', message: 'Too long.' }], 'en_US')).toEqual(['teaser: Too long. (da_DK)']);
    });
});

describe('mapping validation errors onto visibility conditions and the layout', () => {
    const elsewhere: ValidationError[] = [
        { block: 'heading', field: 'until', locale: null, message: 'Until must come after From.', condition: 1 },
        { block: 'heading', field: null, locale: null, message: 'The condition kind "brand" no longer exists.', condition: 2 },
        { block: null, field: 'headline', locale: 'en_US', message: 'This field is required.', layout: true },
        { block: null, field: 'headline', locale: 'da_DK', message: 'This field is required.', layout: true },
    ];

    it('should flag a block whose condition has an error, and select it when publish refuses', () => {
        expect(blockHasErrors(elsewhere, 'heading')).toBe(true);
        expect(firstBlockWithError([block('a'), blockOf('heading', 'heading', [])], elsewhere)).toBe('heading');
    });

    it("should give a condition its messages, each parameter its own, and leave them out of the block's and its fields'", () => {
        expect(conditionMessages(elsewhere, 'heading', 2)).toEqual(['The condition kind "brand" no longer exists.']);
        expect(conditionMessages(elsewhere, 'heading', 1)).toEqual([]);
        expect(conditionFieldMessages(elsewhere, 'heading', 1, 'until')).toEqual(['Until must come after From.']);
        expect(conditionFieldMessages(elsewhere, 'heading', 0, 'until')).toEqual([]);
        expect(conditionFieldMessages(elsewhere, 'other', 1, 'until')).toEqual([]);
        expect(blockMessages(elsewhere, 'heading')).toEqual([]);
        expect(fieldMessages(elsewhere, 'heading', 'until', 'en_US')).toEqual([]);
    });

    it("should give a layout field its messages, saying which locale those of another locale are in, and leave them out of the entry's fields", () => {
        expect(layoutFieldMessages(elsewhere, 'headline', 'en_US')).toEqual(['This field is required.', 'This field is required. (da_DK)']);
        expect(layoutFieldMessages(errors, 'campaign', 'en_US')).toEqual([]);
        expect(entryFieldMessages(elsewhere, 'headline', 'en_US')).toEqual([]);
        expect(entryFieldMessages(errors, 'campaign', 'en_US')).toEqual(['This value should not be blank.']);
    });

    it("should list the layout's messages with the entry's, naming the field each is in", () => {
        expect(entryMessages(elsewhere, 'en_US')).toEqual(['headline: This field is required.', 'headline: This field is required. (da_DK)']);
    });
});

describe('working out where an error is', () => {
    it('should place an error once from where the server says it is', () => {
        const at = (error: Partial<ValidationError>) => placeOf({ block: null, field: null, locale: null, message: 'Wrong', ...error });

        expect(at({})).toEqual({ at: 'entry' });
        expect(at({ field: 'campaign' })).toEqual({ at: 'entryField', field: 'campaign' });
        expect(at({ layout: true })).toEqual({ at: 'layout' });
        expect(at({ field: 'headline', layout: true })).toEqual({ at: 'layoutField', field: 'headline' });
        expect(at({ block: 'heading' })).toEqual({ at: 'block', block: 'heading' });
        expect(at({ block: 'heading', field: 'text' })).toEqual({ at: 'blockField', block: 'heading', field: 'text' });
        expect(at({ block: 'heading', condition: 0 })).toEqual({ at: 'condition', block: 'heading', condition: 0 });
        expect(at({ block: 'heading', field: 'until', condition: 1 })).toEqual({ at: 'conditionParameter', block: 'heading', condition: 1, field: 'until' });
    });

    it("should leave the messages a settings tab shows at its fields out of the entry's, but not those about the layout as a whole", () => {
        const entryErrors: ValidationError[] = [
            { block: null, field: null, locale: null, message: 'Needs at least one Heading block inside.' },
            { block: null, field: 'campaign', locale: null, message: 'This value should not be blank.' },
            { block: null, field: 'teaser', locale: null, message: 'Too long.' },
            { block: null, field: 'headline', locale: null, message: 'This field is required.', layout: true },
            { block: null, field: null, locale: null, message: 'Fill in a headline or a badge.', layout: true },
        ];

        expect(entryMessages(entryErrors, 'en_US', { entryFields: ['campaign'], layoutFields: true })).toEqual([
            'Needs at least one Heading block inside.',
            'teaser: Too long.',
            'Fill in a headline or a badge.',
        ]);
        expect(entryMessages(entryErrors, 'en_US', { entryFields: [], layoutFields: false })).toHaveLength(5);
    });
});
