import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { MediaApi } from '../../src/media/api';
import { MediaLibraryApp } from '../../src/media/MediaLibraryApp';
import type { MediaFilters, MediaFolder, MediaItem, MediaItemChanges, MediaUse, UploadAnswer } from '../../src/media/types';
import { file, libraryConfig, mediaItem } from './fixtures';

/**
 * A library on the server: it lists what it has, newest first, and saves what it's sent. The uses are by media item,
 * and a delete fails with the veto when there is one.
 */
function fakeApi(
    initial: MediaItem[] = [],
    uploaded: (files: readonly File[], copy: boolean) => UploadAnswer = () => ({ created: [], duplicates: [], refused: [] }),
    uses: Record<string, MediaUse[]> = {},
    veto: string | null = null,
) {
    let items = [...initial];
    let folders: MediaFolder[] = [];

    return {
        list: vi.fn(async (filters: MediaFilters) => {
            const shown = items.filter(
                (item) =>
                    (filters.folder === 'all' || (filters.folder === 'unsorted' ? item.folder === null : item.folder === filters.folder)) &&
                    (!filters.missingAlt || item.missingAlt) &&
                    (filters.unused !== true || item.usageCount === 0) &&
                    (filters.kind === null || item.kind === filters.kind) &&
                    (filters.kinds === undefined || filters.kinds.includes(item.kind)),
            );

            return { items: shown, page: 1, pages: 1, total: shown.length };
        }),
        find: vi.fn(async (uuids: readonly string[]) => items.filter((item) => uuids.includes(item.uuid))),
        upload: vi.fn(async (files: readonly File[], folder: string | null, copy: boolean) => {
            const answer = uploaded(files, copy);
            items = [...answer.created.map((item) => ({ ...item, folder })), ...items];

            return answer;
        }),
        save: vi.fn(async (uuid: string, changes: MediaItemChanges) => {
            const saved = items.find((item) => item.uuid === uuid);
            if (saved === undefined) {
                throw new Error(`No ${uuid}`);
            }

            const altTexts = Object.fromEntries(Object.entries({ ...saved.altTexts, ...changes.altTexts }).filter(([, text]) => text !== ''));
            const updated: MediaItem = {
                ...saved,
                ...(changes.name === undefined ? {} : { name: changes.name }),
                ...(changes.folder === undefined ? {} : { folder: changes.folder }),
                ...(changes.focalPoint === undefined ? {} : { focalPoint: changes.focalPoint }),
                ...(changes.poster === undefined ? {} : { poster: changes.poster }),
                altTexts,
                missingAlt: libraryConfig.locales.some(({ code }) => altTexts[code] === undefined),
            };
            items = items.map((item) => (item.uuid === uuid ? updated : item));

            return updated;
        }),
        uses: vi.fn(async (uuid: string) => uses[uuid] ?? []),
        replace: vi.fn(async (uuid: string, replacement: File) => {
            if (replacement.type !== 'image/png') {
                throw new Error(`${replacement.name} isn't an image. Only an image can replace an image.`);
            }

            const replaced = items.find((item) => item.uuid === uuid) as MediaItem;
            const updated = { ...replaced, originalFilename: replacement.name, url: `/media/cms/${uuid}/new/${replacement.name}` };
            items = items.map((item) => (item.uuid === uuid ? updated : item));

            return updated;
        }),
        remove: vi.fn(async (uuid: string) => {
            if (veto !== null) {
                throw new Error(veto);
            }

            items = items.filter((item) => item.uuid !== uuid);
        }),
        folders: vi.fn(async () => folders),
        createFolder: vi.fn(async (name: string, parent: string | null) => {
            const folder = { uuid: `folder-${String(folders.length + 1)}`, name, parent };
            folders = [...folders, folder];

            return folder;
        }),
        updateFolder: vi.fn(async (uuid: string, changes: { name?: string; parent?: string | null }) => {
            const folder = { ...(folders.find((existing) => existing.uuid === uuid) as MediaFolder), ...changes };
            folders = folders.map((existing) => (existing.uuid === uuid ? folder : existing));

            return folder;
        }),
    } satisfies MediaApi;
}

async function openLibrary(api: MediaApi) {
    render(<MediaLibraryApp config={libraryConfig} api={api} />);
    await waitFor(() => expect(api.list).toHaveBeenCalled());
}

function folderTree() {
    return within(screen.getByRole('navigation', { name: 'Folders' }));
}

function grid() {
    return within(screen.getByTestId('media-drop-zone'));
}

async function duplicateOffer(): Promise<HTMLElement> {
    const text = await screen.findByText(/is already in the library as/);

    return text.closest<HTMLElement>('[role="status"]') ?? text;
}

function drop(files: File[]) {
    fireEvent.drop(screen.getByTestId('media-drop-zone'), { dataTransfer: { files, types: ['Files'] } });
}

describe('the media library', () => {
    it('should open All media, and open a new folder inside the open one', async () => {
        const api = fakeApi();
        await openLibrary(api);

        expect(folderTree().getByRole('button', { name: 'All media' })).toHaveAttribute('aria-current', 'true');
        expect(folderTree().getByRole('button', { name: 'Unsorted' })).toBeInTheDocument();

        fireEvent.click(folderTree().getByRole('button', { name: 'New folder' }));
        fireEvent.change(folderTree().getByLabelText('Folder name'), { target: { value: 'Campaigns' } });
        fireEvent.click(folderTree().getByRole('button', { name: 'Create' }));

        await waitFor(() => expect(folderTree().getByRole('button', { name: 'Campaigns' })).toHaveAttribute('aria-current', 'true'));
        expect(api.createFolder).toHaveBeenCalledWith('Campaigns', null);
        expect(api.list).toHaveBeenLastCalledWith({ folder: 'folder-1', search: '', kind: null, missingAlt: false }, 1);

        // Inside the open folder
        fireEvent.click(folderTree().getByRole('button', { name: 'New folder' }));
        fireEvent.change(folderTree().getByLabelText('Folder name'), { target: { value: 'Summer' } });
        fireEvent.click(folderTree().getByRole('button', { name: 'Create' }));
        await waitFor(() => expect(api.createFolder).toHaveBeenLastCalledWith('Summer', 'folder-1'));
    });

    it('should upload dropped files into the open folder, and flag the images without alt text', async () => {
        const api = fakeApi([], (files) => ({ created: files.map((dropped) => mediaItem(dropped.name.replace('.png', ''))), duplicates: [], refused: [] }));
        await openLibrary(api);
        fireEvent.click(folderTree().getByRole('button', { name: 'New folder' }));
        fireEvent.change(folderTree().getByLabelText('Folder name'), { target: { value: 'Campaigns' } });
        fireEvent.click(folderTree().getByRole('button', { name: 'Create' }));
        await waitFor(() => expect(folderTree().getByRole('button', { name: 'Campaigns' })).toHaveAttribute('aria-current', 'true'));

        drop([file('dog.png', 'image/png'), file('cat.png', 'image/png')]);

        await waitFor(() => expect(grid().getAllByRole('listitem')).toHaveLength(2));
        expect(api.upload).toHaveBeenCalledWith([expect.objectContaining({ name: 'dog.png' }), expect.objectContaining({ name: 'cat.png' })], 'folder-1', false);
        expect(grid().getByRole('button', { name: /dog/ })).toHaveTextContent('No alt text');
        expect(grid().getByRole('button', { name: /cat/ })).toHaveTextContent('No alt text');
    });

    it('should refuse a file over the limit or of a type it does not take before sending it, naming the limit', async () => {
        const api = fakeApi([], (files) => ({ created: files.map((dropped) => mediaItem(dropped.name)), duplicates: [], refused: [] }));
        await openLibrary(api);

        drop([file('huge.png', 'image/png', 3 * 1024 * 1024), file('notes.txt', 'text/plain'), file('logo.png', 'image/png')]);

        const alert = await screen.findByRole('alert');
        expect(alert).toHaveTextContent('huge.png is 3 MB, over the upload limit of 2 MB.');
        expect(alert).toHaveTextContent("notes.txt is a type of file the media library doesn't take. It takes JPEG, PNG, SVG, MP4, PDF, up to 2 MB each.");
        await waitFor(() => expect(api.upload).toHaveBeenCalledWith([expect.objectContaining({ name: 'logo.png' })], null, false));
    });

    it('should show why the server refused a file', async () => {
        const api = fakeApi([], () => ({ created: [], duplicates: [], refused: [{ filename: 'photo.png', error: "photo.png couldn't be read as a PNG file." }] }));
        await openLibrary(api);

        drop([file('photo.png', 'image/png')]);

        expect(await screen.findByRole('alert')).toHaveTextContent("photo.png couldn't be read as a PNG file.");
    });

    it('should offer the media item a duplicate matches, or to upload a copy', async () => {
        const existing = mediaItem('logo', { name: 'Logo' });
        const api = fakeApi([existing], (_files, copy) =>
            copy ? { created: [mediaItem('copy')], duplicates: [], refused: [] } : { created: [], duplicates: [{ filename: 'logo-final.png', mediaItem: existing }], refused: [] },
        );
        await openLibrary(api);

        drop([file('logo-final.png', 'image/png')]);

        const offer = await duplicateOffer();
        expect(offer).toHaveTextContent('logo-final.png is already in the library as Logo.');

        fireEvent.click(within(offer).getByRole('button', { name: 'Use the existing one' }));
        expect(grid().getByRole('button', { name: /Logo/ })).toHaveAttribute('aria-pressed', 'true');

        drop([file('logo-final.png', 'image/png')]);
        fireEvent.click(within(await duplicateOffer()).getByRole('button', { name: 'Upload a copy' }));

        await waitFor(() => expect(api.upload).toHaveBeenLastCalledWith([expect.objectContaining({ name: 'logo-final.png' })], null, true));
    });

    it("should save a selected image's name, alt text per locale and focal point together", async () => {
        const api = fakeApi([mediaItem('dog', { name: 'IMG_0042' }), mediaItem('cat')]);
        await openLibrary(api);
        await waitFor(() => expect(grid().getAllByRole('listitem')).toHaveLength(2));

        fireEvent.click(grid().getByRole('button', { name: /IMG_0042/ }));
        const details = within(screen.getByRole('complementary', { name: 'IMG_0042' }));
        fireEvent.change(details.getByLabelText('Name'), { target: { value: 'Golden retriever' } });
        fireEvent.change(details.getByLabelText('English (United States)'), { target: { value: 'A dog on the beach' } });
        fireEvent.change(details.getByLabelText('German (Germany)'), { target: { value: 'Ein Hund am Strand' } });
        const focalPoint = details.getByRole('slider', { name: 'Focal point' });
        fireEvent.keyDown(focalPoint, { key: 'ArrowRight', shiftKey: true });
        fireEvent.keyDown(focalPoint, { key: 'ArrowUp' });
        fireEvent.click(details.getByRole('button', { name: 'Save' }));

        await waitFor(() => expect(details.getByRole('status')).toHaveTextContent('Saved'));
        expect(api.save).toHaveBeenCalledWith('dog', {
            name: 'Golden retriever',
            credit: null,
            altTexts: { en_US: 'A dog on the beach', de_DE: 'Ein Hund am Strand' },
            focalPoint: { x: 0.6, y: 0.49 },
        });
        expect(grid().getByRole('button', { name: /Golden retriever/ })).not.toHaveTextContent('No alt text');
        expect(grid().getByRole('button', { name: /cat/ })).toHaveTextContent('No alt text');
    });

    it("should set a selected video's poster to an image picked in the library's own picker, and remove it, saved with the rest", async () => {
        const api = fakeApi([mediaItem('clip', { kind: 'video', name: 'Launch film', missingAlt: false }), mediaItem('still', { name: 'Launch still' })]);
        await openLibrary(api);
        await waitFor(() => expect(grid().getAllByRole('listitem')).toHaveLength(2));

        fireEvent.click(grid().getByRole('button', { name: /Launch film/ }));
        const poster = within(within(screen.getByRole('complementary', { name: 'Launch film' })).getByRole('group', { name: 'Poster' }));
        expect(poster.getByText('Nothing chosen')).toBeInTheDocument();
        fireEvent.click(poster.getByRole('button', { name: 'Choose from the library' }));

        const picker = within(await screen.findByRole('dialog', { name: 'Choose from the media library' }));
        const pickerGrid = within(picker.getByTestId('media-drop-zone'));
        await waitFor(() => expect(pickerGrid.getAllByRole('listitem')).toHaveLength(1));
        fireEvent.click(pickerGrid.getByRole('button', { name: /Launch still/ }));
        fireEvent.click(picker.getByRole('button', { name: 'Choose' }));
        await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());

        expect(poster.getByText('Launch still')).toBeInTheDocument();
        const details = within(screen.getByRole('complementary', { name: 'Launch film' }));
        fireEvent.click(details.getByRole('button', { name: 'Save' }));
        await waitFor(() => expect(details.getByRole('status')).toHaveTextContent('Saved'));
        expect(api.save).toHaveBeenCalledWith('clip', { name: 'Launch film', credit: null, poster: 'still' });

        fireEvent.click(poster.getByRole('button', { name: 'Remove' }));
        expect(poster.getByText('Nothing chosen')).toBeInTheDocument();
        fireEvent.click(details.getByRole('button', { name: 'Save' }));
        await waitFor(() => expect(api.save).toHaveBeenLastCalledWith('clip', { name: 'Launch film', credit: null, poster: null }));
    });

    it('should flag the selected image while it misses alt text in a locale, until the alt text is saved', async () => {
        const api = fakeApi([mediaItem('dog', { name: 'Dog', altTexts: { en_US: 'A dog' } })]);
        await openLibrary(api);

        fireEvent.click(await grid().findByRole('button', { name: /Dog/ }));
        const details = within(screen.getByRole('complementary', { name: 'Dog' }));
        expect(details.getByText('No alt text')).toBeInTheDocument();

        fireEvent.change(details.getByLabelText('German (Germany)'), { target: { value: 'Ein Hund' } });
        fireEvent.click(details.getByRole('button', { name: 'Save' }));

        await waitFor(() => expect(details.getByRole('status')).toHaveTextContent('Saved'));
        expect(details.queryByText('No alt text')).not.toBeInTheDocument();
    });

    it('should show only the images missing alt text when filtered', async () => {
        const api = fakeApi([mediaItem('dog', { missingAlt: false }), mediaItem('cat')]);
        await openLibrary(api);
        await waitFor(() => expect(grid().getAllByRole('listitem')).toHaveLength(2));

        fireEvent.click(screen.getByRole('checkbox', { name: 'Missing alt text' }));

        await waitFor(() => expect(grid().getAllByRole('listitem')).toHaveLength(1));
        expect(api.list).toHaveBeenLastCalledWith({ folder: 'all', search: '', kind: null, missingAlt: true }, 1);
        expect(grid().getByRole('button', { name: /cat/ })).toBeInTheDocument();
    });

    it('should move the selected media item to another folder, out of the folder that is open', async () => {
        const api = fakeApi([mediaItem('dog')]);
        await api.createFolder('Campaigns', null);
        await openLibrary(api);
        fireEvent.click(folderTree().getByRole('button', { name: 'Unsorted' }));
        await waitFor(() => expect(grid().getAllByRole('listitem')).toHaveLength(1));

        fireEvent.click(grid().getByRole('button', { name: /dog/ }));
        const details = within(screen.getByRole('complementary', { name: 'dog' }));
        fireEvent.change(details.getByLabelText('Move to'), { target: { value: 'folder-1' } });
        fireEvent.click(details.getByRole('button', { name: 'Move' }));

        await waitFor(() => expect(grid().queryAllByRole('listitem')).toHaveLength(0));
        expect(api.save).toHaveBeenCalledWith('dog', { folder: 'folder-1' });
        expect(screen.queryByRole('complementary')).not.toBeInTheDocument();
    });

    it('should show how many entries use each media item, and only those nothing uses when filtered', async () => {
        const api = fakeApi([mediaItem('dog', { usageCount: 2 }), mediaItem('cat', { usageCount: 1 }), mediaItem('bird')]);
        await openLibrary(api);
        await waitFor(() => expect(grid().getAllByRole('listitem')).toHaveLength(3));

        expect(grid().getByRole('button', { name: /dog/ })).toHaveTextContent('2 uses');
        expect(grid().getByRole('button', { name: /cat/ })).toHaveTextContent('1 use');
        expect(grid().getByRole('button', { name: /bird/ })).toHaveTextContent('Not used');

        fireEvent.click(screen.getByRole('checkbox', { name: 'Not used' }));

        await waitFor(() => expect(grid().getAllByRole('listitem')).toHaveLength(1));
        expect(api.list).toHaveBeenLastCalledWith({ folder: 'all', search: '', kind: null, missingAlt: false, unused: true }, 1);
        expect(grid().getByRole('button', { name: /bird/ })).toBeInTheDocument();
    });

    it('should list the entries that use the selected media item with links, and keep Delete disabled while any do', async () => {
        const api = fakeApi([mediaItem('logo', { usageCount: 2 })], undefined, {
            logo: [
                { entry: 'e1', title: 'Summer sale', contentType: 'Page', url: '/admin/cms/entry/e1', draft: true, published: true },
                { entry: 'e2', title: null, contentType: 'Blog post', url: '/admin/cms/entry/e2', draft: true, published: false },
            ],
        });
        await openLibrary(api);
        await waitFor(() => expect(grid().getAllByRole('listitem')).toHaveLength(1));

        fireEvent.click(grid().getByRole('button', { name: /logo/ }));
        const details = within(screen.getByRole('complementary', { name: 'logo' }));
        const usedIn = within(await details.findByRole('list', { name: 'Used in' }));

        expect(usedIn.getByRole('link', { name: 'Summer sale' })).toHaveAttribute('href', '/admin/cms/entry/e1');
        expect(usedIn.getAllByRole('listitem')[0]).toHaveTextContent('Page · Published');
        expect(usedIn.getByRole('link', { name: 'Untitled' })).toHaveAttribute('href', '/admin/cms/entry/e2');
        expect(usedIn.getAllByRole('listitem')[1]).toHaveTextContent('Blog post · Draft');
        expect(details.getByRole('button', { name: 'Delete' })).toBeDisabled();
        expect(details.getByText("It can't be deleted while it's used.")).toBeInTheDocument();
    });

    it("should replace the selected media item's file after showing where it's used, and show why one is refused", async () => {
        const api = fakeApi([mediaItem('logo', { usageCount: 1 })], undefined, {
            logo: [{ entry: 'e1', title: 'Summer sale', contentType: 'Page', url: '/admin/cms/entry/e1', draft: true, published: true }],
        });
        await openLibrary(api);
        await waitFor(() => expect(grid().getAllByRole('listitem')).toHaveLength(1));
        fireEvent.click(grid().getByRole('button', { name: /logo/ }));
        const details = within(screen.getByRole('complementary', { name: 'logo' }));
        await details.findByRole('list', { name: 'Used in' });

        fireEvent.click(details.getByRole('button', { name: 'Replace file' }));
        const dialog = within(screen.getByRole('dialog', { name: 'Replace file' }));
        expect(dialog.getByText(/takes effect at once wherever it's used/)).toBeInTheDocument();
        expect(dialog.getByRole('link', { name: 'Summer sale' })).toHaveAttribute('href', '/admin/cms/entry/e1');
        expect(dialog.getByRole('button', { name: 'Replace' })).toBeDisabled();
        expect(dialog.getByLabelText('New file')).toHaveAttribute('accept', 'image/jpeg,image/png,image/svg+xml');

        // One of another kind is refused
        fireEvent.change(dialog.getByLabelText('New file'), { target: { files: [file('prices.pdf', 'application/pdf')] } });
        fireEvent.click(dialog.getByRole('button', { name: 'Replace' }));
        expect(await dialog.findByRole('alert')).toHaveTextContent("prices.pdf isn't an image. Only an image can replace an image.");

        // One that's too big isn't sent
        fireEvent.change(dialog.getByLabelText('New file'), { target: { files: [file('huge.png', 'image/png', 3 * 1024 * 1024)] } });
        fireEvent.click(dialog.getByRole('button', { name: 'Replace' }));
        await waitFor(() => expect(dialog.getByRole('alert')).toHaveTextContent('huge.png is 3 MB, over the upload limit of 2 MB.'));
        expect(api.replace).toHaveBeenCalledTimes(1);

        fireEvent.change(dialog.getByLabelText('New file'), { target: { files: [file('logo-2027.png', 'image/png')] } });
        fireEvent.click(dialog.getByRole('button', { name: 'Replace' }));

        await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
        expect(api.replace).toHaveBeenLastCalledWith('logo', expect.objectContaining({ name: 'logo-2027.png' }));
        expect(details.getByRole('link', { name: 'logo-2027.png' })).toHaveAttribute('href', '/media/cms/logo/new/logo-2027.png');
    });

    it('should delete a media item nothing uses once the editor confirms', async () => {
        const api = fakeApi([mediaItem('dog'), mediaItem('cat')]);
        await openLibrary(api);
        await waitFor(() => expect(grid().getAllByRole('listitem')).toHaveLength(2));
        fireEvent.click(grid().getByRole('button', { name: /dog/ }));
        const details = within(screen.getByRole('complementary', { name: 'dog' }));
        expect(await details.findByText("It isn't used anywhere.")).toBeInTheDocument();

        fireEvent.click(details.getByRole('button', { name: 'Delete' }));
        const dialog = within(screen.getByRole('dialog', { name: 'Delete dog?' }));
        fireEvent.click(dialog.getByRole('button', { name: 'Delete' }));

        await waitFor(() => expect(grid().getAllByRole('listitem')).toHaveLength(1));
        expect(api.remove).toHaveBeenCalledWith('dog');
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(screen.queryByRole('complementary')).not.toBeInTheDocument();
    });

    it('should keep a media item whose delete a listener vetoes, and show its message', async () => {
        const api = fakeApi([mediaItem('logo')], undefined, {}, 'The logo stays until the rebrand');
        await openLibrary(api);
        await waitFor(() => expect(grid().getAllByRole('listitem')).toHaveLength(1));
        fireEvent.click(grid().getByRole('button', { name: /logo/ }));
        const details = within(screen.getByRole('complementary', { name: 'logo' }));
        await details.findByText("It isn't used anywhere.");

        fireEvent.click(details.getByRole('button', { name: 'Delete' }));
        const dialog = within(screen.getByRole('dialog', { name: 'Delete logo?' }));
        fireEvent.click(dialog.getByRole('button', { name: 'Delete' }));

        expect(await dialog.findByRole('alert')).toHaveTextContent('The logo stays until the rebrand');
        // Behind the dialog, still in the library
        expect(grid().getAllByRole('listitem', { hidden: true })).toHaveLength(1);
    });
});
