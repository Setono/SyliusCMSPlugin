import { useState, type FormEvent } from 'react';
import { useTranslator } from '../i18n';
import { useMediaLibrary, useMediaLibraryContext } from './context';
import { Field } from './Field';
import { flattenTree, folderAndDescendants, folderTree } from './folders';
import styles from './MediaLibrary.module.css';
import { ALL_MEDIA, UNSORTED } from './types';

type Editing = 'create' | 'rename' | 'move' | null;

/**
 * All media, Unsorted and the nested folders. A new folder goes inside the open one, and the open folder can be renamed
 * or moved. See "Folders" in docs/spec/media.md.
 */
export function FolderTree() {
    const t = useTranslator();
    const { store } = useMediaLibraryContext();
    const folders = useMediaLibrary((state) => state.folders);
    const open = useMediaLibrary((state) => state.filters.folder);
    const [editing, setEditing] = useState<Editing>(null);
    const [name, setName] = useState('');
    const [parent, setParent] = useState('');

    const openFolder = folders.find(({ uuid }) => uuid === open) ?? null;
    const nodes = flattenTree(folderTree(folders));

    const start = (mode: Editing): void => {
        setEditing(mode);
        setName(mode === 'rename' ? (openFolder?.name ?? '') : '');
        setParent(openFolder?.parent ?? '');
    };

    const submit = async (event: FormEvent): Promise<void> => {
        event.preventDefault();
        const library = store.getState();
        const done =
            editing === 'create'
                ? await library.createFolder(name)
                : editing === 'rename' && openFolder !== null
                  ? await library.renameFolder(openFolder.uuid, name)
                  : editing === 'move' && openFolder !== null
                    ? await library.moveFolder(openFolder.uuid, parent === '' ? null : parent)
                    : false;

        if (done) {
            setEditing(null);
        }
    };

    const item = (folder: string, label: string, depth = 0) => (
        <li key={folder}>
            <button
                type="button"
                className={styles.folder}
                style={{ paddingInlineStart: `calc(var(--builder-space-2) + ${String(depth)} * var(--builder-space-4))` }}
                aria-current={open === folder ? 'true' : undefined}
                onClick={() => {
                    setEditing(null);
                    void store.getState().setFilters({ folder });
                }}
            >
                {label}
            </button>
        </li>
    );

    const unavailable = openFolder === null ? new Set<string>() : folderAndDescendants(folders, openFolder.uuid);

    return (
        <nav className={styles.folders} aria-label={t('setono_sylius_cms.builder.media.folders')}>
            <ul className={styles.folderList}>
                {item(ALL_MEDIA, t('setono_sylius_cms.builder.media.all_media'))}
                {item(UNSORTED, t('setono_sylius_cms.builder.media.unsorted'))}
                {nodes.map(({ folder, depth }) => item(folder.uuid, folder.name, depth))}
            </ul>

            {editing === null ? (
                <div className={styles.folderActions}>
                    <button type="button" className={styles.secondary} onClick={() => start('create')}>
                        {t('setono_sylius_cms.builder.media.new_folder')}
                    </button>
                    {openFolder !== null && (
                        <>
                            <button type="button" className={styles.secondary} onClick={() => start('rename')}>
                                {t('setono_sylius_cms.builder.media.rename_folder')}
                            </button>
                            <button type="button" className={styles.secondary} onClick={() => start('move')}>
                                {t('setono_sylius_cms.builder.media.move_folder')}
                            </button>
                        </>
                    )}
                </div>
            ) : (
                <form className={styles.folderForm} onSubmit={(event) => void submit(event)}>
                    {editing === 'move' ? (
                        <Field label={t('setono_sylius_cms.builder.media.move_to')}>
                            {(id) => (
                                <select id={id} className={styles.select} value={parent} onChange={(event) => setParent(event.target.value)}>
                                    <option value="">{t('setono_sylius_cms.builder.media.top_level')}</option>
                                    {nodes
                                        .filter(({ folder }) => !unavailable.has(folder.uuid))
                                        .map(({ folder, depth }) => (
                                            <option key={folder.uuid} value={folder.uuid}>
                                                {`${' '.repeat(depth)}${folder.name}`}
                                            </option>
                                        ))}
                                </select>
                            )}
                        </Field>
                    ) : (
                        <Field label={t('setono_sylius_cms.builder.media.folder_name')}>
                            {(id) => <input id={id} className={styles.input} value={name} required autoFocus onChange={(event) => setName(event.target.value)} />}
                        </Field>
                    )}
                    <div className={styles.actions}>
                        <button type="submit" className={styles.primary}>
                            {editing === 'create'
                                ? t('setono_sylius_cms.builder.media.create_folder')
                                : editing === 'rename'
                                  ? t('setono_sylius_cms.builder.media.rename_folder')
                                  : t('setono_sylius_cms.builder.media.move')}
                        </button>
                        <button type="button" className={styles.secondary} onClick={() => setEditing(null)}>
                            {t('setono_sylius_cms.builder.media.cancel')}
                        </button>
                    </div>
                </form>
            )}
        </nav>
    );
}
