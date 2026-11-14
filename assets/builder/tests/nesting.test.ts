import { describe, expect, it } from 'vitest';
import { allowedIn, canMove, createNestingRules, insertableIn, placement, type NestingRules } from '../src/nesting';
import type { BlockTypeDefinition, StoredBlock } from '../src/types';
import { blockOf, blockTypes, page, spacer } from './fixtures';

const rules = createNestingRules(blockTypes, page.blocks);

/** Columns "cols" with as many Columns as given, keyed c1, c2 and so on. */
function columnsWith(count: number, key = 'cols'): StoredBlock {
    return blockOf(
        'columns',
        key,
        Array.from({ length: count }, (_, i) => blockOf('column', `${key}-c${i + 1}`)),
    );
}

function codes(types: readonly BlockTypeDefinition[]): string[] {
    return types.map((type) => type.code);
}

describe('what a spot in the tree allows', () => {
    it('should allow at the top level what the content type allows', () => {
        expect(allowedIn(rules, [], null).map((allowed) => allowed.type)).toEqual(['group', 'columns', 'spacer', 'heading']);
    });

    it("should allow inside a block what its block type allows, with min and max, and nothing inside a block that holds none", () => {
        const tree = [columnsWith(2), blockOf('heading', 'h')];

        expect(allowedIn(rules, tree, 'cols')).toEqual([{ type: 'column', min: 2, max: 4 }]);
        expect(allowedIn(rules, tree, 'cols-c1').map((allowed) => allowed.type)).toEqual(['group', 'columns', 'spacer', 'heading']);
        expect(allowedIn(rules, tree, 'h')).toEqual([]);
        expect(allowedIn(rules, tree, 'missing')).toEqual([]);
    });

    it('should allow nothing at the top level of a content type without a block tree', () => {
        expect(allowedIn(createNestingRules(blockTypes, null), [], null)).toEqual([]);
    });
});

describe('the block picker', () => {
    it('should offer only the block types allowed at the spot', () => {
        const tree = [columnsWith(2)];

        expect(codes(insertableIn(rules, tree, null))).toEqual(['group', 'columns', 'spacer', 'heading']);
        expect(codes(insertableIn(rules, tree, 'cols'))).toEqual(['column']);
    });

    it('should leave out a disabled block type', () => {
        const withSpacerDisabled = createNestingRules(
            blockTypes.map((type) => (type === spacer ? { ...spacer, disabled: true } : type)),
            page.blocks,
        );

        expect(codes(insertableIn(withSpacerDisabled, [], null))).toEqual(['group', 'columns', 'heading']);
    });

    it('should leave out a block type once its spot holds as many as its max', () => {
        expect(codes(insertableIn(rules, [columnsWith(3)], 'cols'))).toEqual(['column']);
        expect(codes(insertableIn(rules, [columnsWith(4)], 'cols'))).toEqual([]);
    });
});

describe('dropping a dragged block', () => {
    const tree = [blockOf('heading', 'a'), columnsWith(2), blockOf('heading', 'b')];

    it('should place it before or after the block it is dropped on, in that block list', () => {
        expect(placement(tree, 'b', 'cols', 'before')).toEqual({ parent: null, index: 1 });
        expect(placement(tree, 'b', 'a', 'after')).toEqual({ parent: null, index: 1 });
        expect(placement(tree, 'a', 'cols-c2', 'before')).toEqual({ parent: 'cols', index: 1 });
    });

    it('should count the index once the dragged block is taken out of the list', () => {
        expect(placement(tree, 'a', 'b', 'after')).toEqual({ parent: null, index: 2 });
        expect(placement(tree, 'a', 'cols', 'after')).toEqual({ parent: null, index: 1 });
    });

    it('should place it at the end of the children of the block it is dropped into', () => {
        expect(placement(tree, 'a', 'cols-c2', 'inside')).toEqual({ parent: 'cols-c2', index: 0 });
        expect(placement(tree, 'cols-c1', 'cols', 'inside')).toEqual({ parent: 'cols', index: 1 });
    });

    it('should place nothing for a block the tree does not have', () => {
        expect(placement(tree, 'a', 'missing', 'before')).toBeNull();
    });
});

describe('moving a block', () => {
    const moves = (r: NestingRules, tree: StoredBlock[], key: string, parent: string | null) => canMove(r, tree, key, parent);

    it('should allow a move to a spot that allows the block type', () => {
        const tree = [blockOf('heading', 'h'), columnsWith(2)];

        expect(moves(rules, tree, 'h', 'cols-c2')).toBe(true);
        expect(moves(rules, tree, 'cols', null)).toBe(true);
    });

    it('should refuse a Column at the top level and a block inside a block type that holds none', () => {
        const tree = [blockOf('heading', 'h'), blockOf('heading', 'h2'), columnsWith(2)];

        expect(moves(rules, tree, 'cols-c1', null)).toBe(false);
        expect(moves(rules, tree, 'h', 'h2')).toBe(false);
        expect(moves(rules, tree, 'h', 'cols')).toBe(false);
    });

    it('should refuse a move into the block itself or into a block inside it', () => {
        const tree = [blockOf('group', 'g', [blockOf('group', 'inner')])];

        expect(moves(rules, tree, 'g', 'g')).toBe(false);
        expect(moves(rules, tree, 'g', 'inner')).toBe(false);
    });

    it('should refuse a move into a spot that already holds as many as its max', () => {
        const tree = [columnsWith(3, 'a'), columnsWith(4, 'b')];

        expect(moves(rules, tree, 'a-c1', 'b')).toBe(false);
        expect(moves(rules, tree, 'b-c1', 'a')).toBe(true);
    });

    it('should allow reordering a full spot', () => {
        expect(moves(rules, [columnsWith(4)], 'cols-c1', 'cols')).toBe(true);
    });

    it('should refuse a move that leaves its spot with fewer than its min', () => {
        const tree = [columnsWith(2, 'a'), columnsWith(3, 'b')];

        expect(moves(rules, tree, 'a-c1', 'b')).toBe(false);
    });

    it('should refuse a move of a block the tree does not have, or into one it does not have', () => {
        const tree = [blockOf('heading', 'h')];

        expect(moves(rules, tree, 'missing', null)).toBe(false);
        expect(moves(rules, tree, 'h', 'missing')).toBe(false);
    });
});
