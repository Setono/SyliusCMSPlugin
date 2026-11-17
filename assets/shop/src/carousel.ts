/**
 * The carousel: a track of slides that scrolls with CSS scroll-snap, and works without this script. The script adds
 * previous and next buttons, a dot per slide and autoplay, which pauses while the pointer or focus is inside.
 * See "Carousel" in docs/spec/blocks.md
 */

/** The names of the buttons the script adds. slide has %number% for the slide's number, from 1. */
export interface CarouselLabels {
    previous: string;
    next: string;
    slide: string;
}

interface Carousel {
    /** Whether the carousel is as the script left it: a re-render, such as the builder's, takes the controls away. */
    isIntact(): boolean;
    destroy(): void;
}

const carousels = new WeakMap<Element, Carousel>();

/** How long the track must be still after a scroll before the current dot follows it. */
const SCROLL_SETTLE_MS = 100;

const ICONS = {
    previous: '<path d="M10 3 5 8l5 5"/>',
    next: '<path d="m6 3 5 5-5 5"/>',
};

/**
 * Initialises every carousel in the root, and the root itself when it's one. A carousel that's initialised and intact
 * is left as it is, so initialising twice does nothing.
 */
export function initCarousels(root: Document | Element, labels: CarouselLabels): void {
    const found = Array.from(root.querySelectorAll<HTMLElement>('.cms-carousel'));
    if (root instanceof HTMLElement && root.matches('.cms-carousel')) {
        found.unshift(root);
    }

    found.forEach((element) => initCarousel(element, labels));
}

function initCarousel(root: HTMLElement, labels: CarouselLabels): void {
    const existing = carousels.get(root);
    if (existing?.isIntact()) {
        return;
    }

    existing?.destroy();
    carousels.set(root, createCarousel(root, labels));
}

function createCarousel(root: HTMLElement, labels: CarouselLabels): Carousel {
    const document = root.ownerDocument;
    const window = document.defaultView;
    const track = root.querySelector<HTMLElement>(':scope > .cms-carousel__track');
    const slides = track === null ? [] : slidesOf(track);
    const autoplayAttribute = root.getAttribute('data-cms-autoplay');
    const isIntact = (added: Element[]): boolean =>
        root.querySelector(':scope > .cms-carousel__track') === track &&
        (track === null || sameElements(slidesOf(track), slides)) &&
        added.every((element) => element.parentNode === root) &&
        root.getAttribute('data-cms-autoplay') === autoplayAttribute;

    if (track === null || slides.length < 2 || window === null) {
        return { isIntact: () => isIntact([]), destroy: () => {} };
    }

    const listening = new AbortController();
    const { signal } = listening;
    let index = 0;
    let autoplayTimer: number | undefined;
    let scrollTimer: number | undefined;
    const holds = new Set<'pointer' | 'focus'>();

    const previousButton = button(document, 'cms-carousel__prev', labels.previous, ICONS.previous);
    const nextButton = button(document, 'cms-carousel__next', labels.next, ICONS.next);
    const dotList = document.createElement('ul');
    dotList.className = 'cms-carousel__dots';
    const dots = slides.map((_, i) => {
        const item = document.createElement('li');
        const dot = button(document, 'cms-carousel__dot', labels.slide.replace('%number%', String(i + 1)));
        dot.addEventListener('click', () => goTo(i), { signal });
        item.append(dot);
        dotList.append(item);

        return dot;
    });
    const added = [previousButton, nextButton, dotList];
    root.append(...added);

    const rtl = (): boolean => window.getComputedStyle(track).direction === 'rtl';
    // Where a box starts along the track: its left edge, or its right edge right to left
    const startOf = (element: Element): number => {
        const box = element.getBoundingClientRect();

        return rtl() ? -box.right : box.left;
    };
    const reachedEnd = (): boolean => {
        const scrollable = track.scrollWidth - track.clientWidth;

        return scrollable > 1 && Math.abs(track.scrollLeft) >= scrollable - 1;
    };

    const show = (i: number): void => {
        index = i;
        dots.forEach((dot, j) => (j === i ? dot.setAttribute('aria-current', 'true') : dot.removeAttribute('aria-current')));
    };

    // The track's scroll-behavior decides whether it scrolls smoothly, and its scroll-snap where it stops
    const goTo = (i: number): void => {
        show(i);
        const slide = slides[i];
        if (slide !== undefined) {
            const offset = startOf(slide) - startOf(track);
            track.scrollLeft += rtl() ? -offset : offset;
        }
    };
    const next = (): void => goTo(index >= slides.length - 1 || reachedEnd() ? 0 : index + 1);
    const previous = (): void => goTo(index <= 0 ? slides.length - 1 : index - 1);

    // After the shopper scrolls the track, the current slide is the one that starts nearest the track's start
    const follow = (): void => {
        const start = startOf(track);
        let nearest = 0;
        slides.forEach((slide, i) => {
            if (Math.abs(startOf(slide) - start) < Math.abs(startOf(slides[nearest]!) - start) - 0.5) {
                nearest = i;
            }
        });
        show(nearest);
    };

    const autoplay = Number(autoplayAttribute);
    const stopAutoplay = (): void => {
        window.clearInterval(autoplayTimer);
        autoplayTimer = undefined;
    };
    const destroy = (): void => {
        listening.abort();
        stopAutoplay();
        window.clearTimeout(scrollTimer);
        added.forEach((element) => element.remove());
    };
    const play = (): void => {
        if (autoplay > 0 && Number.isFinite(autoplay) && holds.size === 0 && autoplayTimer === undefined) {
            autoplayTimer = window.setInterval(() => (root.isConnected ? next() : destroy()), autoplay);
        }
    };
    const hold = (reason: 'pointer' | 'focus'): void => {
        holds.add(reason);
        stopAutoplay();
    };
    const release = (reason: 'pointer' | 'focus'): void => {
        holds.delete(reason);
        play();
    };

    previousButton.addEventListener('click', previous, { signal });
    nextButton.addEventListener('click', next, { signal });
    track.addEventListener(
        'scroll',
        () => {
            window.clearTimeout(scrollTimer);
            scrollTimer = window.setTimeout(follow, SCROLL_SETTLE_MS);
        },
        { passive: true, signal },
    );
    root.addEventListener('pointerenter', () => hold('pointer'), { signal });
    root.addEventListener('pointerleave', () => release('pointer'), { signal });
    root.addEventListener('focusin', () => hold('focus'), { signal });
    root.addEventListener(
        'focusout',
        (event) => {
            if (!(event.relatedTarget instanceof Node && root.contains(event.relatedTarget))) {
                release('focus');
            }
        },
        { signal },
    );

    show(0);
    if (root.contains(document.activeElement)) {
        hold('focus');
    }
    play();

    return { isIntact: () => isIntact(added), destroy };
}

function slidesOf(track: Element): Element[] {
    return Array.from(track.children).filter((child) => child.classList.contains('cms-carousel__slide'));
}

function sameElements(a: Element[], b: Element[]): boolean {
    return a.length === b.length && a.every((element, i) => element === b[i]);
}

function button(document: Document, className: string, label: string, icon?: string): HTMLButtonElement {
    const element = document.createElement('button');
    element.type = 'button';
    element.className = className;
    element.setAttribute('aria-label', label);
    if (icon !== undefined) {
        element.innerHTML = `<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${icon}</svg>`;
    }

    return element;
}
