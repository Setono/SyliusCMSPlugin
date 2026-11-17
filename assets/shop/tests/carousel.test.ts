import { fireEvent, within } from '@testing-library/dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { findBlock } from '../../builder/src/bridge/markers';
import { morphBlock } from '../../builder/src/bridge/morph';
import { start } from '../src/start';

function slides(texts: string[]): string {
    return texts.map((text) => `<div class="cms-carousel__slide"><h2 class="cms-heading">${text}</h2></div>`).join('');
}

function carouselHtml(texts: string[], attributes = 'data-cms-per-view="1"'): string {
    return `<div class="cms-carousel" ${attributes}><div class="cms-carousel__track">${slides(texts)}</div></div>`;
}

function carousel(index = 0): HTMLElement {
    const found = document.querySelectorAll<HTMLElement>('.cms-carousel')[index];
    if (found === undefined) {
        throw new Error('No carousel in the page');
    }

    return found;
}

function dots(root: HTMLElement): HTMLElement[] {
    return within(root).queryAllByRole('button', { name: /^Slide \d+$/ });
}

/** The number of the current slide's dot, from 1. */
function current(root: HTMLElement): number {
    return dots(root).findIndex((dot) => dot.getAttribute('aria-current') === 'true') + 1;
}

/** Lays the slides out side by side, each as wide as the track, as the stylesheet does with one slide per view. */
function layOut(root: HTMLElement, width = 100): HTMLElement {
    const track = root.querySelector<HTMLElement>('.cms-carousel__track');
    if (track === null) {
        throw new Error('The carousel has no track');
    }

    const box = (left: number) => ({ left, right: left + width, top: 0, bottom: 0, width, height: 0, x: left, y: 0, toJSON: () => ({}) });
    track.getBoundingClientRect = () => box(0);
    Array.from(track.children).forEach((slide, i) => {
        slide.getBoundingClientRect = () => box(i * width - track.scrollLeft);
    });

    return track;
}

const stops: Array<() => void> = [];

/** Starts the script on the page, as cms.js does with its own element. */
function run(script: HTMLScriptElement | null = null): void {
    stops.push(start(document, script));
}

afterEach(() => {
    stops.splice(0).forEach((stop) => stop());
    document.body.innerHTML = '';
    vi.useRealTimers();
});

describe('the carousel script', () => {
    beforeEach(() => {
        document.body.innerHTML = `<main>${carouselHtml(['Summer sale', 'Free shipping', 'New in'])}</main>`;
        run();
    });

    it('should add a previous and a next button and a dot per slide, the first one current', () => {
        const root = carousel();

        expect(root.querySelector(':scope > .cms-carousel__prev')).toHaveAccessibleName('Previous slide');
        expect(root.querySelector(':scope > .cms-carousel__next')).toHaveAccessibleName('Next slide');
        expect(root.querySelectorAll(':scope > .cms-carousel__dots .cms-carousel__dot')).toHaveLength(3);
        expect(dots(root).map((dot) => dot.textContent === '' && dot.getAttribute('type'))).toEqual(['button', 'button', 'button']);
        expect(current(root)).toBe(1);
    });

    it('should do nothing when it initialises a carousel twice', () => {
        const root = carousel();
        const next = root.querySelector('.cms-carousel__next');

        run();
        document.dispatchEvent(new CustomEvent('cms:render', { bubbles: true }));
        root.dispatchEvent(new CustomEvent('cms:render', { bubbles: true }));

        expect(root.querySelectorAll('.cms-carousel__prev')).toHaveLength(1);
        expect(root.querySelectorAll('.cms-carousel__next')).toHaveLength(1);
        expect(root.querySelector('.cms-carousel__next')).toBe(next);
        expect(root.querySelectorAll('.cms-carousel__dots')).toHaveLength(1);
        expect(dots(root)).toHaveLength(3);

        // One click moves one slide, so the buttons listen once
        fireEvent.click(next!);
        expect(current(root)).toBe(2);
    });

    it('should scroll the track to the next slide, and from the last back to the first', () => {
        const root = carousel();
        const track = layOut(root);
        const next = within(root).getByRole('button', { name: 'Next slide' });

        fireEvent.click(next);
        expect(track.scrollLeft).toBe(100);
        expect(current(root)).toBe(2);

        fireEvent.click(next);
        expect(track.scrollLeft).toBe(200);

        fireEvent.click(next);
        expect(track.scrollLeft).toBe(0);
        expect(current(root)).toBe(1);
    });

    it('should scroll the track to the previous slide, and from the first to the last', () => {
        const root = carousel();
        const track = layOut(root);
        const previous = within(root).getByRole('button', { name: 'Previous slide' });

        fireEvent.click(previous);
        expect(track.scrollLeft).toBe(200);
        expect(current(root)).toBe(3);

        fireEvent.click(previous);
        expect(track.scrollLeft).toBe(100);
        expect(current(root)).toBe(2);
    });

    it('should scroll to the slide of the dot clicked', () => {
        const root = carousel();
        const track = layOut(root);

        fireEvent.click(within(root).getByRole('button', { name: 'Slide 3' }));

        expect(track.scrollLeft).toBe(200);
        expect(current(root)).toBe(3);
    });

    it('should make the dot of the slide the shopper swiped to current once the track stops scrolling', () => {
        vi.useFakeTimers();
        const root = carousel();
        const track = layOut(root);

        track.scrollLeft = 190;
        fireEvent.scroll(track);
        vi.advanceTimersByTime(200);

        expect(current(root)).toBe(3);
    });
});

describe('the dots of the carousel script', () => {
    it('should match the slides', () => {
        document.body.innerHTML = carouselHtml(['1', '2', '3', '4', '5'], 'data-cms-per-view="3"');
        run();

        expect(dots(carousel()).map((dot) => dot.getAttribute('aria-label'))).toEqual(['Slide 1', 'Slide 2', 'Slide 3', 'Slide 4', 'Slide 5']);
    });

    it('should leave out the buttons and the dots of a carousel of one slide', () => {
        document.body.innerHTML = carouselHtml(['Only']);
        run();

        expect(carousel().querySelectorAll('button')).toHaveLength(0);
        expect(carousel().querySelector('.cms-carousel__dots')).toBeNull();
    });

    it("should name the buttons with the labels on the script's element, in the shop's locale", () => {
        document.body.innerHTML = carouselHtml(['Sommerudsalg', 'Fri fragt']);
        const script = document.createElement('script');
        script.dataset.cmsLabelPrevious = 'Forrige';
        script.dataset.cmsLabelNext = 'Næste';
        script.dataset.cmsLabelSlide = 'Slide nr. %number%';

        run(script);

        const root = carousel();
        expect(within(root).getByRole('button', { name: 'Forrige' })).toHaveClass('cms-carousel__prev');
        expect(within(root).getByRole('button', { name: 'Næste' })).toHaveClass('cms-carousel__next');
        expect(within(root).getAllByRole('button', { name: /^Slide nr\. \d$/ })).toHaveLength(2);
    });
});

describe('autoplay', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        document.body.innerHTML = `<main>${carouselHtml(['Summer sale', 'Free shipping', 'New in'], 'data-cms-per-view="1" data-cms-autoplay="5000"')}<a href="/elsewhere">Elsewhere</a></main>`;
        run();
    });

    it('should move to the next slide every data-cms-autoplay milliseconds', () => {
        const root = carousel();

        vi.advanceTimersByTime(4999);
        expect(current(root)).toBe(1);

        vi.advanceTimersByTime(1);
        expect(current(root)).toBe(2);

        vi.advanceTimersByTime(10000);
        expect(current(root)).toBe(1);
    });

    it('should pause while focus is inside the carousel', () => {
        const root = carousel();
        const next = within(root).getByRole('button', { name: 'Next slide' });

        next.focus();
        vi.advanceTimersByTime(20000);
        expect(current(root)).toBe(1);

        // Moving focus within the carousel keeps it paused
        within(root).getByRole('button', { name: 'Slide 2' }).focus();
        vi.advanceTimersByTime(20000);
        expect(current(root)).toBe(1);

        document.querySelector<HTMLElement>('a[href="/elsewhere"]')!.focus();
        vi.advanceTimersByTime(5000);
        expect(current(root)).toBe(2);
    });

    it('should pause while the pointer is inside the carousel', () => {
        const root = carousel();

        root.dispatchEvent(new Event('pointerenter'));
        vi.advanceTimersByTime(20000);
        expect(current(root)).toBe(1);

        root.dispatchEvent(new Event('pointerleave'));
        vi.advanceTimersByTime(5000);
        expect(current(root)).toBe(2);
    });

    it('should not autoplay a carousel without data-cms-autoplay', () => {
        document.body.innerHTML = carouselHtml(['Summer sale', 'Free shipping']);
        run();

        vi.advanceTimersByTime(60000);

        expect(current(carousel())).toBe(1);
    });

    it('should stop once the carousel is no longer in the page', () => {
        const root = carousel();
        const clearInterval = vi.spyOn(window, 'clearInterval');

        root.remove();
        vi.advanceTimersByTime(5000);

        expect(clearInterval).toHaveBeenCalled();
        expect(vi.getTimerCount()).toBe(0);
    });
});

describe('the cms:render event', () => {
    it('should initialise every carousel inside the block the builder patched in', () => {
        document.body.innerHTML = '<main><section></section></main>';
        run();
        const section = document.querySelector('section')!;

        section.innerHTML = carouselHtml(['Summer sale', 'Free shipping']) + carouselHtml(['A', 'B', 'C']);
        section.dispatchEvent(new CustomEvent('cms:render', { bubbles: true, detail: { key: 'a', type: 'group' } }));

        expect(dots(carousel(0))).toHaveLength(2);
        expect(dots(carousel(1))).toHaveLength(3);
    });

    it('should initialise the carousel the event comes from', () => {
        document.body.innerHTML = '<main></main>';
        run();

        document.querySelector('main')!.innerHTML = carouselHtml(['Summer sale', 'Free shipping']);
        carousel().dispatchEvent(new CustomEvent('cms:render', { bubbles: true, detail: { key: 'a', type: 'carousel' } }));

        expect(dots(carousel())).toHaveLength(2);
    });

    it('should initialise again a carousel the bridge morphed a new slide into, autoplaying it once', () => {
        vi.useFakeTimers();
        const marked = (texts: string[]) => `<!--cms:block a carousel-->${carouselHtml(texts, 'data-cms-per-view="1" data-cms-autoplay="5000"')}<!--/cms:block a-->`;
        document.body.innerHTML = `<main>${marked(['Summer sale', 'Free shipping'])}</main>`;
        run();
        const root = carousel();

        // What the bridge does with a patch: it morphs the block's new HTML into the page, then dispatches cms:render
        const block = morphBlock(findBlock(document, 'a')!, marked(['Summer sale', 'Free shipping', 'New in']));
        expect(carousel()).toBe(root);
        expect(root.querySelector('.cms-carousel__next')).toBeNull();
        root.dispatchEvent(new CustomEvent('cms:render', { bubbles: true, detail: { key: block.key, type: block.type } }));

        expect(root.querySelectorAll('.cms-carousel__prev')).toHaveLength(1);
        expect(dots(root)).toHaveLength(3);

        vi.advanceTimersByTime(5000);
        expect(current(root)).toBe(2);
        expect(vi.getTimerCount()).toBe(1);
    });
});
