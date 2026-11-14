/**
 * Morphs a block's new HTML into the page: nodes that stay the same kind are updated in place, so elements keep their
 * identity, focus and state where they can, and only what changed is touched.
 */
import { blockNodes, findBlocks, type BlockRange } from './markers';

/** Parses HTML the way innerHTML does: scripts in it don't run. */
export function parseHtml(document: Document, html: string): ChildNode[] {
    const template = document.createElement('template');
    template.innerHTML = html;

    return Array.from(template.content.childNodes);
}

/**
 * Replaces the block's output with its new HTML, which carries its own markers, and answers the block as it is now.
 * The new HTML is expected to be the block's output alone, as preview_render answers it.
 */
export function morphBlock(block: BlockRange, html: string): BlockRange {
    const document = block.start.ownerDocument;
    const parent = block.start.parentNode;
    if (parent === null) {
        return block;
    }

    const incoming = parseHtml(document, html);
    const markers = findBlocks(wrap(document, incoming)).find((found) => found.key === block.key);
    const [start, end] = markers === undefined ? [null, null] : [markers.start, markers.end];
    const inner = start === null ? incoming : incoming.slice(incoming.indexOf(start) + 1, incoming.indexOf(end));

    if (start !== null) {
        block.start.data = start.data;
    }

    morphNodes(parent, blockNodes(block), inner, block.end);

    return { key: block.key, type: markers?.type ?? block.type, start: block.start, end: block.end };
}

/** Puts parsed nodes in a fragment, so they can be searched as one tree. */
function wrap(document: Document, nodes: ChildNode[]): DocumentFragment {
    const fragment = document.createDocumentFragment();
    fragment.append(...nodes);

    return fragment;
}

/** Morphs one list of sibling nodes into another, inserting new nodes before the reference node (or at the end). */
function morphNodes(parent: Node, current: ChildNode[], next: ChildNode[], before: Node | null): void {
    const count = Math.max(current.length, next.length);

    for (let i = 0; i < count; i++) {
        const existing = current[i];
        const incoming = next[i];

        if (existing !== undefined && incoming !== undefined) {
            if (sameKind(existing, incoming)) {
                morphNode(existing, incoming);
            } else {
                parent.replaceChild(incoming, existing);
            }
        } else if (incoming !== undefined) {
            parent.insertBefore(incoming, before);
        } else {
            existing?.remove();
        }
    }
}

function sameKind(a: Node, b: Node): boolean {
    if (a.nodeType !== b.nodeType) {
        return false;
    }

    return a.nodeType !== Node.ELEMENT_NODE || ((a as Element).namespaceURI === (b as Element).namespaceURI && a.nodeName === b.nodeName);
}

function morphNode(existing: ChildNode, incoming: ChildNode): void {
    if (existing.nodeType !== Node.ELEMENT_NODE) {
        if (existing.nodeValue !== incoming.nodeValue) {
            existing.nodeValue = incoming.nodeValue;
        }

        return;
    }

    morphAttributes(existing as Element, incoming as Element);
    morphNodes(existing, Array.from(existing.childNodes), Array.from(incoming.childNodes), null);
}

function morphAttributes(existing: Element, incoming: Element): void {
    for (const { name } of Array.from(existing.attributes)) {
        if (!incoming.hasAttribute(name)) {
            existing.removeAttribute(name);
        }
    }

    for (const { name, value } of Array.from(incoming.attributes)) {
        if (existing.getAttribute(name) !== value) {
            existing.setAttribute(name, value);
        }
    }
}
