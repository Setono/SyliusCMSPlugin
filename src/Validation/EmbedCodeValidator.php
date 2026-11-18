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
 * hand-made save, and so is one whose code differs from that published block's. Blocks published while it was on keep
 * their place, wherever they've moved to in the tree since, and can be removed, but not rewritten.
 * See "Validation" in docs/spec/publishing.md and ADR 0018
 *
 * @internal
 */
final readonly class EmbedCodeValidator
{
    /** The Embed code block's field that holds its code */
    private const CODE_FIELD = 'code';

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

        $published = self::embedCodes($publishedTree);

        $errors = [];
        foreach (self::embedCodes($tree) as $key => $code) {
            if (!array_key_exists($key, $published)) {
                $errors[] = new ValidationError($key, null, null, $this->translator->trans('setono_sylius_cms.block.embed_code_off', [], 'validators'));
            } elseif ($code !== $published[$key]) {
                $errors[] = new ValidationError($key, self::CODE_FIELD, null, $this->translator->trans('setono_sylius_cms.block.embed_code_off_changed', [], 'validators'));
            }
        }

        return $errors;
    }

    /**
     * @return array<string, mixed> the stored code of the tree's embed_code blocks by key, depth first
     */
    private static function embedCodes(StoredTree $tree): array
    {
        $codes = [];
        foreach ($tree->allBlocks() as $block) {
            if (EmbedCode::CODE === $block->type) {
                $codes[$block->key] = self::code($block);
            }
        }

        return $codes;
    }

    private static function code(StoredBlock $block): mixed
    {
        return $block->fields[self::CODE_FIELD] ?? null;
    }
}
