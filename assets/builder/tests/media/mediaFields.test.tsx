import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { FieldRow } from '../../src/form/FieldRow';
import { createTranslator, TranslatorProvider } from '../../src/i18n';
import type { MediaApi } from '../../src/media/api';
import { MediaPickerProvider } from '../../src/media/picker';
import type { MediaFilters, MediaItem, UploadAnswer } from '../../src/media/types';
import type { FieldSchema, JsonValue } from '../../src/types';
import { file, libraryConfig, mediaItem } from './fixtures';

const strings = {
    ...libraryConfig.strings,
    'setono_sylius_cms.builder.media.pick': 'Choose from the library',
    'setono_sylius_cms.builder.media.add': 'Add from the library',
    'setono_sylius_cms.builder.media.replace': 'Replace',
    'setono_sylius_cms.builder.media.remove': 'Remove',
    'setono_sylius_cms.builder.media.nothing_picked': 'Nothing chosen',
    'setono_sylius_cms.builder.media.deleted_media_item': 'This media item was deleted, so it isn’t shown.',
    'setono_sylius_cms.builder.media.picker_title': 'Choose from the media library',
    'setono_sylius_cms.builder.media.choose': 'Choose',
    'setono_sylius_cms.builder.media.choose_count': 'Choose %count%',
    'setono_sylius_cms.builder.media.at_most': 'Up to %max%',
    'setono_sylius_cms.builder.move_up': 'Move up',
    'setono_sylius_cms.builder.move_down': 'Move down',
};

const mediaSchema: FieldSchema = {
    type: 'string',
    format: 'uuid',
    'x-cms-type': 'media',
    'x-cms-label': 'Image',
    'x-cms-help': null,
    'x-cms-required': false,
    'x-cms-translatable': false,
    'x-cms-options': { kinds: ['image'] },
};

const mediaListSchema: FieldSchema = {
    type: 'array',
    items: { type: 'string', format: 'uuid' },
    'x-cms-type': 'media_list',
    'x-cms-label': 'Gallery',
    'x-cms-help': null,
    'x-cms-required': false,
    'x-cms-translatable': false,
    'x-cms-options': { kinds: ['image', 'document'], max: 3 },
};

const beach = mediaItem('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d01', { name: 'Beach' });
const forest = mediaItem('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d02', { name: 'Forest' });
const prices = mediaItem('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d03', { name: 'Prices', kind: 'document', mimeType: 'application/pdf', url: '/media/cms/prices.pdf' });
const trailer = mediaItem('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d04', { name: 'Trailer', kind: 'video', mimeType: 'video/mp4', url: '/media/cms/trailer.mp4' });
const deleted = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d09';

/** A library on the server with these media items, newest first, which lists those of the kinds asked for. */
function fakeApi(initial: MediaItem[] = [beach, forest, prices, trailer], uploaded: (files: readonly File[]) => UploadAnswer = () => ({ created: [], duplicates: [], refused: [] })) {
    let items = [...initial];

    return {
        list: vi.fn(async (filters: MediaFilters) => {
            const kinds = filters.kind === null ? (filters.kinds ?? null) : [filters.kind];
            const shown = items.filter((item) => kinds === null || kinds.includes(item.kind));

            return { items: shown, page: 1, pages: 1, total: shown.length };
        }),
        find: vi.fn(async (uuids: readonly string[]) => items.filter((item) => uuids.includes(item.uuid))),
        upload: vi.fn(async (files: readonly File[]) => {
            const answer = uploaded(files);
            items = [...answer.created, ...items];

            return answer;
        }),
        save: vi.fn(async () => {
            throw new Error('Not saved here');
        }),
        uses: vi.fn(async () => []),
        replace: vi.fn(async () => {
            throw new Error('Not replaced here');
        }),
        remove: vi.fn(async () => {
            throw new Error('Not deleted here');
        }),
        folders: vi.fn(async () => []),
        createFolder: vi.fn(async () => {
            throw new Error('No folders here');
        }),
        updateFolder: vi.fn(async () => {
            throw new Error('No folders here');
        }),
    } satisfies MediaApi;
}

/** A field whose value lives in the test, as the builder's store keeps it, with every change recorded. */
function Field({ schema, initial, onChange }: { schema: FieldSchema; initial?: JsonValue; onChange: (value: JsonValue | null) => void }) {
    const [value, setValue] = useState<JsonValue | undefined>(initial);

    return (
        <FieldRow
            id="field"
            schema={schema}
            value={value}
            locale="en_US"
            onChange={(next) => {
                onChange(next);
                setValue(next ?? undefined);
            }}
        />
    );
}

function renderField(schema: FieldSchema, initial?: JsonValue, api: MediaApi = fakeApi()) {
    const onChange = vi.fn();
    render(
        <TranslatorProvider value={createTranslator(strings)}>
            <MediaPickerProvider api={api} config={{ locales: libraryConfig.locales, media: libraryConfig.media }}>
                <Field schema={schema} initial={initial} onChange={onChange} />
            </MediaPickerProvider>
        </TranslatorProvider>,
    );

    return { onChange, api };
}

function picker() {
    return within(screen.getByRole('dialog', { name: 'Choose from the media library' }));
}

async function gridItem(name: string): Promise<HTMLElement> {
    return picker().findByRole('button', { name: new RegExp(`\\b${name}\\b`) });
}

describe('a media field', () => {
    it('should say nothing is chosen, with a button the field label names to choose from the library', () => {
        renderField(mediaSchema);

        expect(screen.getByText('Nothing chosen')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Image' })).toHaveTextContent('Choose from the library');
    });

    it('should open the library as a picker of the kinds the field takes, and store the UUID of the media item picked', async () => {
        const { onChange, api } = renderField(mediaSchema);

        fireEvent.click(screen.getByRole('button', { name: 'Image' }));

        await gridItem('Beach');
        expect(api.list).toHaveBeenLastCalledWith(expect.objectContaining({ kind: 'image', kinds: ['image'] }), 1);
        expect(picker().queryByRole('button', { name: /\bPrices\b/ })).not.toBeInTheDocument();
        expect(picker().getByRole('combobox', { name: 'Kind' })).toBeDisabled();
        expect(picker().getByRole('button', { name: 'Choose' })).toBeDisabled();

        fireEvent.click(await gridItem('Forest'));
        fireEvent.click(picker().getByRole('button', { name: 'Choose' }));

        await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
        expect(onChange).toHaveBeenCalledWith(forest.uuid);
        expect(screen.getByText('Forest')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Image' })).toHaveTextContent('Replace');
    });

    it('should show the name of the media item it holds, which it looks up by UUID', async () => {
        const { api } = renderField(mediaSchema, beach.uuid);

        expect(await screen.findByText('Beach')).toBeInTheDocument();
        expect(api.find).toHaveBeenCalledWith([beach.uuid]);
    });

    it('should flag a media item that was deleted', async () => {
        renderField(mediaSchema, deleted);

        expect(await screen.findByText('This media item was deleted, so it isn’t shown.')).toBeInTheDocument();
    });

    it('should remove the media item', async () => {
        const { onChange } = renderField(mediaSchema, beach.uuid);
        await screen.findByText('Beach');

        fireEvent.click(screen.getByRole('button', { name: 'Remove' }));

        expect(onChange).toHaveBeenCalledWith(null);
        expect(screen.getByText('Nothing chosen')).toBeInTheDocument();
    });

    it('should leave the field as it is when the picker is cancelled', async () => {
        const { onChange } = renderField(mediaSchema, beach.uuid);
        await screen.findByText('Beach');

        fireEvent.click(screen.getByRole('button', { name: 'Image' }));
        fireEvent.click(await gridItem('Forest'));
        fireEvent.click(picker().getByRole('button', { name: 'Cancel' }));

        await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
        expect(onChange).not.toHaveBeenCalled();
        expect(screen.getByText('Beach')).toBeInTheDocument();
    });

    it('should pick an image uploaded from inside the picker', async () => {
        const dog = mediaItem('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d05', { name: 'dog' });
        const { onChange } = renderField(mediaSchema, undefined, fakeApi([beach], () => ({ created: [dog], duplicates: [], refused: [] })));

        fireEvent.click(screen.getByRole('button', { name: 'Image' }));
        await gridItem('Beach');
        fireEvent.change(picker().getByLabelText('Upload'), { target: { files: [file('dog.png', 'image/png')] } });

        await waitFor(() => expect(picker().getByRole('button', { name: /^dog/ })).toHaveAttribute('aria-pressed', 'true'));
        fireEvent.click(picker().getByRole('button', { name: 'Choose' }));

        await waitFor(() => expect(onChange).toHaveBeenCalledWith(dog.uuid));
        expect(screen.getByText('dog')).toBeInTheDocument();
    });
});

describe('a media list field', () => {
    it('should add the media items picked in the picker, in the order they were picked, skipping those it holds', async () => {
        const { onChange, api } = renderField(mediaListSchema, [prices.uuid]);
        await screen.findByText('Prices');

        fireEvent.click(screen.getByRole('button', { name: 'Gallery' }));

        await gridItem('Beach');
        expect(api.list).toHaveBeenLastCalledWith(expect.objectContaining({ kind: null, kinds: ['image', 'document'] }), 1);
        expect(picker().queryByRole('button', { name: /\bTrailer\b/ })).not.toBeInTheDocument();

        fireEvent.click(await gridItem('Forest'));
        fireEvent.click(await gridItem('Prices'));
        fireEvent.click(await gridItem('Beach'));
        expect(picker().getByRole('button', { name: 'Choose 3' })).toBeEnabled();
        fireEvent.click(picker().getByRole('button', { name: 'Choose 3' }));

        await waitFor(() => expect(onChange).toHaveBeenCalledWith([prices.uuid, forest.uuid, beach.uuid]));
        expect(within(screen.getByRole('list', { name: 'Gallery' })).getAllByRole('listitem').map((item) => item.textContent)).toEqual([
            expect.stringContaining('Prices'),
            expect.stringContaining('Forest'),
            expect.stringContaining('Beach'),
        ]);
    });

    it('should hold no more than its most, and say how many it holds at most', async () => {
        renderField(mediaListSchema, [prices.uuid, beach.uuid, forest.uuid]);

        await screen.findByText('Forest');
        expect(screen.getByRole('button', { name: 'Gallery' })).toBeDisabled();
        expect(screen.getByText('Up to 3')).toBeInTheDocument();
    });

    it('should leave out what is picked beyond its most', async () => {
        const lake = mediaItem('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d06', { name: 'Lake' });
        const { onChange } = renderField(mediaListSchema, [prices.uuid, beach.uuid], fakeApi([beach, forest, prices, lake]));
        await screen.findByText('Beach');

        fireEvent.click(screen.getByRole('button', { name: 'Gallery' }));
        fireEvent.click(await gridItem('Forest'));
        fireEvent.click(await gridItem('Lake'));
        fireEvent.click(picker().getByRole('button', { name: 'Choose 2' }));

        await waitFor(() => expect(onChange).toHaveBeenCalledWith([prices.uuid, beach.uuid, forest.uuid]));
    });

    it('should move a media item up and down, and remove it', async () => {
        const { onChange } = renderField(mediaListSchema, [prices.uuid, beach.uuid]);
        const list = within(await screen.findByRole('list', { name: 'Gallery' }));
        await screen.findByText('Beach');

        fireEvent.click(within(list.getAllByRole('listitem')[1] as HTMLElement).getByRole('button', { name: 'Move up' }));
        expect(onChange).toHaveBeenLastCalledWith([beach.uuid, prices.uuid]);

        fireEvent.click(within(list.getAllByRole('listitem')[1] as HTMLElement).getByRole('button', { name: 'Remove' }));
        expect(onChange).toHaveBeenLastCalledWith([beach.uuid]);
    });

    it('should keep a media item that was deleted in the list, flagged, so it can be removed', async () => {
        renderField(mediaListSchema, [deleted, beach.uuid]);

        expect(await screen.findByText('This media item was deleted, so it isn’t shown.')).toBeInTheDocument();
        expect(within(screen.getByRole('list', { name: 'Gallery' })).getAllByRole('listitem')).toHaveLength(2);
    });
});
