import { beforeEach, describe, expect, it } from 'vitest';
import { findBlock } from '../../src/bridge/markers';
import { morphBlock } from '../../src/bridge/morph';

function marked(key: string, html: string, type = 'heading'): string {
    return `<!--cms:block ${key} ${type}-->${html}<!--/cms:block ${key}-->`;
}

function block(key: string) {
    const found = findBlock(document, key);
    if (found === null) {
        throw new Error(`No block ${key} in the page`);
    }

    return found;
}

describe('morphing a block', () => {
    beforeEach(() => {
        document.body.innerHTML = `<main>${marked('a', '<h2 class="cms-heading">Hello</h2>')}<p id="after">After</p></main>`;
    });

    it('should change only what changed, keeping the elements that stay', () => {
        const heading = document.querySelector('h2');

        morphBlock(block('a'), marked('a', '<h2 class="cms-heading">Hello world</h2>'));

        expect(document.querySelector('h2')).toBe(heading);
        expect(heading).toHaveTextContent('Hello world');
        expect(document.querySelector('main')?.innerHTML).toBe(`${marked('a', '<h2 class="cms-heading">Hello world</h2>')}<p id="after">After</p>`);
    });

    it('should update, add and remove attributes', () => {
        document.body.innerHTML = marked('a', '<h2 class="cms-heading" title="Old" data-gone="1">Hello</h2>');

        morphBlock(block('a'), marked('a', '<h2 class="cms-heading cms-dark" title="New">Hello</h2>'));

        const heading = document.querySelector('h2');
        expect(heading).toHaveAttribute('class', 'cms-heading cms-dark');
        expect(heading).toHaveAttribute('title', 'New');
        expect(heading).not.toHaveAttribute('data-gone');
    });

    it('should replace an element whose tag changed', () => {
        morphBlock(block('a'), marked('a', '<h3 class="cms-heading">Hello</h3>'));

        expect(document.querySelector('h2')).toBeNull();
        expect(document.querySelector('main > h3')).toHaveTextContent('Hello');
    });

    it('should add and remove nodes, keeping them between the markers', () => {
        morphBlock(block('a'), marked('a', '<h2 class="cms-heading">Hello</h2><p>More</p><p>And more</p>'));
        expect(document.querySelector('main')?.innerHTML).toBe(`${marked('a', '<h2 class="cms-heading">Hello</h2><p>More</p><p>And more</p>')}<p id="after">After</p>`);

        morphBlock(block('a'), marked('a', '<p>Less</p>'));
        expect(document.querySelector('main')?.innerHTML).toBe(`${marked('a', '<p>Less</p>')}<p id="after">After</p>`);
    });

    it('should morph the blocks inside it, keeping their markers', () => {
        document.body.innerHTML = marked('parent', `<section>${marked('child', '<h2>Child</h2>')}</section>`, 'section');
        const child = document.querySelector('h2');

        morphBlock(block('parent'), marked('parent', `<section class="wide">${marked('child', '<h2>Child changed</h2>')}</section>`, 'section'));

        expect(document.querySelector('h2')).toBe(child);
        expect(child).toHaveTextContent('Child changed');
        expect(block('child').type).toBe('heading');
    });

    it('should answer the block as it is now', () => {
        const morphed = morphBlock(block('a'), marked('a', '<p>Now a paragraph</p>', 'text'));

        expect(morphed).toMatchObject({ key: 'a', type: 'text' });
        expect(morphed.start.nextSibling).toBe(document.querySelector('main > p'));
        expect(morphed.end.previousSibling).toBe(document.querySelector('main > p'));
    });
});
