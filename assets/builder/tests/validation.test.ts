import { describe, expect, it } from 'vitest';
import type { ValidationError } from '../src/types';
import { blockHasErrors, blockMessages, entryMessages, fieldMessages, firstBlockWithError } from '../src/validation';
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
