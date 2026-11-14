import * as DropdownMenu from '@radix-ui/react-dropdown-menu';
import { useBuilder, useBuilderState } from '../context';
import { useTranslator } from '../i18n';
import styles from './AddBlockMenu.module.css';
import { PlusIcon } from './icons';

/** Lists the block types; choosing one appends a block of that type to the top level. */
export function AddBlockMenu() {
    const t = useTranslator();
    const { store } = useBuilder();
    const blockTypes = useBuilderState((state) => state.blockTypes);

    return (
        <DropdownMenu.Root>
            <DropdownMenu.Trigger asChild>
                <button type="button" className={styles.trigger}>
                    <PlusIcon />
                    {t('setono_sylius_cms.builder.add_block')}
                </button>
            </DropdownMenu.Trigger>
            <DropdownMenu.Portal>
                <DropdownMenu.Content className={styles.menu} align="start" sideOffset={4}>
                    {blockTypes.map((blockType) => (
                        <DropdownMenu.Item key={blockType.code} className={styles.item} onSelect={() => store.getState().addBlock(blockType.code)}>
                            {blockType.label}
                        </DropdownMenu.Item>
                    ))}
                </DropdownMenu.Content>
            </DropdownMenu.Portal>
        </DropdownMenu.Root>
    );
}
