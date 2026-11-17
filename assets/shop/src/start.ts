import { type CarouselLabels, initCarousels } from './carousel';

/** The labels when the script's element has none, such as in a project's own bundle. */
const ENGLISH: CarouselLabels = { previous: 'Previous slide', next: 'Next slide', slide: 'Slide %number%' };

/**
 * Initialises every carousel in the page once it's parsed, and every carousel inside the target of a cms:render event,
 * which the builder's bridge dispatches from each block it patches in. The labels of the buttons come from the
 * script's element, translated by the shop (shop/javascript.html.twig). Returns a function that stops listening.
 * See "Carousel" in docs/spec/blocks.md and "The bridge" in docs/spec/builder.md
 */
export function start(document: Document, script: HTMLOrSVGScriptElement | null): () => void {
    const data = script instanceof HTMLElement ? script.dataset : {};
    const labels: CarouselLabels = {
        previous: data.cmsLabelPrevious || ENGLISH.previous,
        next: data.cmsLabelNext || ENGLISH.next,
        slide: data.cmsLabelSlide || ENGLISH.slide,
    };

    const onLoad = (): void => initCarousels(document, labels);
    const onRender = (event: Event): void => {
        if (event.target instanceof Element) {
            initCarousels(event.target, labels);
        }
    };

    document.addEventListener('cms:render', onRender);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', onLoad, { once: true });
    } else {
        onLoad();
    }

    return () => {
        document.removeEventListener('cms:render', onRender);
        document.removeEventListener('DOMContentLoaded', onLoad);
    };
}
