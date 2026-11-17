<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Validation;

use Setono\SyliusCMSPlugin\Block\EmbedCode;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The Embed code block's publish rule: while setono_sylius_cms.embed_code is off, an embed_code block whose key isn't
 * already an embed_code block in the entry's published tree is refused, however it got into the draft, such as through a
 * hand-made save. Blocks published while it was on keep their place, wherever they've moved to in the tree since.
 * See "Validation" in docs/spec/publishing.md and ADR 0018
 *
 * @internal
 */
final readonly class EmbedCodeValidator
{
    /**
     * @param bool $enabled setono_sylius_cms.embed_code
     */
    public function __construct(
        private bool $enabled,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param StoredTree $tree the tree that would be published
     * @param StoredTree $publishedTree the entry's published tree, which is empty for an entry that was never published
     *
     * @return list<ValidationError>
     */
    public function validate(StoredTree $tree, StoredTree $publishedTree): array
    {
        if ($this->enabled) {
            return [];
        }

        $published = self::embedCodeKeys($publishedTree);

        $errors = [];
        foreach (self::embedCodeKeys($tree) as $key) {
            if (!in_array($key, $published, true)) {
                $errors[] = new ValidationError($key, null, null, $this->translator->trans('setono_sylius_cms.block.embed_code_off', [], 'validators'));
            }
        }

        return $errors;
    }

    /**
     * @return list<string> the keys of the tree's embed_code blocks, depth first
     */
    private static function embedCodeKeys(StoredTree $tree): array
    {
        return array_values(array_map(
            static fn (StoredBlock $block): string => $block->key,
            array_filter($tree->allBlocks(), static fn (StoredBlock $block): bool => EmbedCode::CODE === $block->type),
        ));
    }
}
