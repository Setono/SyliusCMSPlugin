import type { MediaFolder } from './types';

/** A folder with the folders inside it, by name. */
export interface FolderNode {
    folder: MediaFolder;
    children: FolderNode[];
    depth: number;
}

/**
 * The folder tree under All media and Unsorted, each level by name. A folder whose parent is missing goes at the top,
 * so no folder is lost.
 */
export function folderTree(folders: readonly MediaFolder[]): FolderNode[] {
    const known = new Set(folders.map(({ uuid }) => uuid));
    const byParent = new Map<string | null, MediaFolder[]>();
    for (const folder of folders) {
        const parent = folder.parent !== null && known.has(folder.parent) ? folder.parent : null;
        byParent.set(parent, [...(byParent.get(parent) ?? []), folder]);
    }

    const build = (parent: string | null, depth: number, seen: ReadonlySet<string>): FolderNode[] =>
        [...(byParent.get(parent) ?? [])]
            .filter(({ uuid }) => !seen.has(uuid))
            .sort((a, b) => a.name.localeCompare(b.name))
            .map((folder) => ({ folder, depth, children: build(folder.uuid, depth + 1, new Set([...seen, folder.uuid])) }));

    return build(null, 0, new Set());
}

/** The tree in the order it's shown, depth first. */
export function flattenTree(nodes: readonly FolderNode[]): FolderNode[] {
    return nodes.flatMap((node) => [node, ...flattenTree(node.children)]);
}

/** The folder and every folder inside it, which it can't be moved into. */
export function folderAndDescendants(folders: readonly MediaFolder[], uuid: string): Set<string> {
    const found = new Set([uuid]);
    let grew = true;
    while (grew) {
        grew = false;
        for (const folder of folders) {
            if (folder.parent !== null && found.has(folder.parent) && !found.has(folder.uuid)) {
                found.add(folder.uuid);
                grew = true;
            }
        }
    }

    return found;
}
