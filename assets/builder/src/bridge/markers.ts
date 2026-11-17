/**
 * The HTML comment markers the plugin brackets each block's output with in the builder's preview
 * (src/Preview/PreviewBlockRenderer.php): <!--cms:block {key} {type}--> before it and <!--/cms:block {key}--> after it.
 * The format is internal, shared only by that class and the bridge.
 */

/** A block in the page: everything between its two markers, which are siblings. */
export interface BlockRange {
    key: string;
    type: string;
    start: Comment;
    end: Comment;
}

const START = /^cms:block (\S+) (\S+)$/;
const END = /^\/cms:block (\S+)$/;

/** Every block in the page or under the node, in document order, so a block comes before the blocks inside it. */
export function findBlocks(root: Node): BlockRange[] {
    const document = root.ownerDocument ?? (root as Document);
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_COMMENT);
    const blocks: Array<Omit<BlockRange, 'end'> & { end: Comment | null }> = [];
    const open = new Map<string, Omit<BlockRange, 'end'> & { end: Comment | null }>();

    for (let node = walker.nextNode(); node !== null; node = walker.nextNode()) {
        const comment = node as Comment;
        const start = START.exec(comment.data);
        if (start !== null) {
            const block = { key: start[1] ?? '', type: start[2] ?? '', start: comment, end: null };
            blocks.push(block);
            open.set(block.key, block);

            continue;
        }

        const end = END.exec(comment.data);
        const block = end === null ? undefined : open.get(end[1] ?? '');
        if (block !== undefined && block.start.parentNode === comment.parentNode) {
            block.end = comment;
            open.delete(block.key);
        }
    }

    return blocks.filter((block): block is BlockRange => block.end !== null);
}

export function findBlock(root: Node, key: string): BlockRange | null {
    return findBlocks(root).find((block) => block.key === key) ?? null;
}

/** The innermost block the node is part of, or null when it's outside every block. */
export function blockContaining(root: Node, node: Node): BlockRange | null {
    const containing = findBlocks(root).filter((block) => blockHolds(block, node));

    return containing[containing.length - 1] ?? null;
}

/** Whether the node is part of the block's output. */
export function blockHolds(block: BlockRange, node: Node): boolean {
    return (block.start.compareDocumentPosition(node) & Node.DOCUMENT_POSITION_FOLLOWING) !== 0 && (block.end.compareDocumentPosition(node) & Node.DOCUMENT_POSITION_PRECEDING) !== 0;
}

/** The nodes between the block's markers. */
export function blockNodes(block: BlockRange): ChildNode[] {
    const nodes: ChildNode[] = [];
    for (let node = block.start.nextSibling; node !== null && node !== block.end; node = node.nextSibling) {
        nodes.push(node);
    }

    return nodes;
}

/** The elements at the top of the block, whose boxes make up its box. */
export function blockElements(block: BlockRange): Element[] {
    return blockNodes(block).filter((node): node is Element => node.nodeType === Node.ELEMENT_NODE);
}

/** The block's root element: the first element of its output. */
export function rootElement(block: BlockRange): Element | null {
    return blockElements(block)[0] ?? null;
}

/**
 * A field that cms_field() printed and marked (src/Preview/FieldMarker.php), so editors can type it in place:
 * <!--cms:field {key} {field} {type}--> before it and <!--/cms:field {key} {field}--> after it. Everything between
 * the two markers, which are siblings, is the field's output.
 */
export interface FieldRange {
    /** The key of the block the field belongs to. */
    key: string;
    field: string;
    /** The field type: text, textarea or rich_text. */
    type: string;
    start: Comment;
    end: Comment;
}

const FIELD_START = /^cms:field (\S+) (\S+) (\S+)$/;
const FIELD_END = /^\/cms:field (\S+) (\S+)$/;

/** Every marked field in the page or under the node, in document order. */
export function findFields(root: Node): FieldRange[] {
    const document = root.ownerDocument ?? (root as Document);
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_COMMENT);
    const fields: FieldRange[] = [];
    let open: Omit<FieldRange, 'end'> | null = null;

    for (let node = walker.nextNode(); node !== null; node = walker.nextNode()) {
        const comment = node as Comment;
        const start = FIELD_START.exec(comment.data);
        if (start !== null) {
            open = { key: start[1] ?? '', field: start[2] ?? '', type: start[3] ?? '', start: comment };

            continue;
        }

        // A field's output holds no other fields, so its end marker closes the one opened last
        const end = FIELD_END.exec(comment.data);
        if (end !== null && open !== null && open.key === end[1] && open.field === end[2] && open.start.parentNode === comment.parentNode) {
            fields.push({ ...open, end: comment });
            open = null;
        }
    }

    return fields;
}

/** The nodes between the field's markers. */
export function fieldNodes(field: FieldRange): ChildNode[] {
    const nodes: ChildNode[] = [];
    for (let node = field.start.nextSibling; node !== null && node !== field.end; node = node.nextSibling) {
        nodes.push(node);
    }

    return nodes;
}

/**
 * The keys of the blocks in the page whose own output holds a notice of a link that doesn't resolve, which the plugin
 * follows such a block's output with in the builder's preview (src/Preview/UnresolvedLinkNotices.php): an element
 * with data-cms-unresolved-link. In document order, each once.
 */
export function blocksWithUnresolvedLinks(root: Document | Element): string[] {
    const keys: string[] = [];
    for (const notice of Array.from(root.querySelectorAll('[data-cms-unresolved-link]'))) {
        const key = blockContaining(root, notice)?.key;
        if (key !== undefined && !keys.includes(key)) {
            keys.push(key);
        }
    }

    return keys;
}
