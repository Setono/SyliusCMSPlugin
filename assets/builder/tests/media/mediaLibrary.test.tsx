import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { MediaApi } from '../../src/media/api';
import { MediaLibraryApp } from '../../src/media/MediaLibraryApp';
import type { MediaFilters, MediaFolder, MediaItem, MediaItemChanges, UploadAnswer } from '../../src/media/types';
import { file, libraryConfig, mediaItem } from './fixtures';

/** A library on the server: it lists what it has, newest first, and saves what it's sent. */
function fakeApi(initial: MediaItem[] = [], uploaded: (files: readonly File[], copy: boolean) => UploadAnswer = () => ({ created: [], duplicates: [], refused: [] })) {
    let items = [...initial];
    let folders: MediaFolder[] = [];

    return {
        list: vi.fn(async (filters: MediaFilters) => {
            const shown = items.filter(
                (item) =>
                    (filters.folder === 'all' || (filters.folder === 'unsorted' ? item.folder === null : item.folder === filters.folder)) &&
                    (!filters.missingAlt || item.missingAlt) &&
                    (filters.kind === null || item.kind === filters.kind),
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
                altTexts,
                missingAlt: libraryConfig.locales.some(({ code }) => altTexts[code] === undefined),
            };
            items = items.map((item) => (item.uuid === uuid ? updated : item));

            return updated;
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
});
