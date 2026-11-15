<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Validation;

use Setono\SyliusCMSPlugin\Registry\AllowedBlockType;
use Setono\SyliusCMSPlugin\Registry\BlockTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\NestingRulesInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Validates a block tree as publish does. See "Validation" in docs/spec/publishing.md
 *
 * @internal
 */
final readonly class BlockTreeValidator
{
    public function __construct(
        private BlockTypeRegistryInterface $blockTypes,
        private NestingRulesInterface $nestingRules,
        private FieldValidator $fieldValidator,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param string $contentType the code of the content type of the entry the tree is in
     * @param list<string> $locales the entry's locales, which translatable fields are validated in
     *
     * @return list<ValidationError>
     */
    public function validate(StoredTree $tree, string $contentType, array $locales): array
    {
        return $this->validateList($tree->blocks, $this->nestingRules->topLevel($contentType), null, $locales);
    }

    /**
     * Validates one list of blocks against what its spot may hold. Too few or too many blocks of a type is an error of
     * the block that holds the list, or of the entry at the top level
     *
     * @param list<StoredBlock> $blocks
     * @param list<AllowedBlockType> $allowed
     * @param string|null $parent the key of the block that holds the list, or null at the top level
     * @param list<string> $locales
     *
     * @return list<ValidationError>
     */
    private function validateList(array $blocks, array $allowed, ?string $parent, array $locales): array
    {
        $allowedCodes = array_map(static fn (AllowedBlockType $type): string => $type->code, $allowed);
        $errors = [];

        foreach ($blocks as $block) {
            // A block of a type the code no longer has is kept as it is, with what's inside it
            if (!$this->blockTypes->has($block->type)) {
                $errors[] = new ValidationError($block->key, null, null, $this->translator->trans('setono_sylius_cms.block.unknown_type', ['%type%' => $block->type], 'validators'));

                continue;
            }

            $blockType = $this->blockTypes->get($block->type);
            if (!in_array($block->type, $allowedCodes, true)) {
                $errors[] = new ValidationError($block->key, null, null, $this->translator->trans('setono_sylius_cms.block.not_allowed', ['%block%' => $this->label($blockType)], 'validators'));
            }

            foreach ($blockType->fields as $name => $field) {
                $stored = $block->fields[$name] ?? null;
                $errors = [...$errors, ...($field->translatable
                    ? $this->fieldValidator->validateTranslatable($blockType->class, $field, $stored, $locales, $block->key)
                    : $this->fieldValidator->validateShared($blockType->class, $field, $stored, $block->key))];
            }

            $errors = [...$errors, ...$this->validateList($block->children, $this->nestingRules->children($block->type), $block->key, $locales)];
        }

        foreach ($allowed as $type) {
            $count = count(array_filter($blocks, static fn (StoredBlock $block): bool => $block->type === $type->code));
            $parameters = ['%type%' => $this->label($this->blockTypes->get($type->code))];

            if (null !== $type->min && $count < $type->min) {
                $errors[] = new ValidationError($parent, null, null, $this->translator->trans('setono_sylius_cms.block.too_few', ['%count%' => $type->min] + $parameters, 'validators'));
            }

            if (null !== $type->max && $count > $type->max) {
                $errors[] = new ValidationError($parent, null, null, $this->translator->trans('setono_sylius_cms.block.too_many', ['%count%' => $type->max] + $parameters, 'validators'));
            }
        }

        return $errors;
    }

    private function label(BlockTypeMetadata $blockType): string
    {
        return $this->translator->trans($blockType->label);
    }
}
