<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\SanitizingFieldHandlerInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;
use Setono\SyliusCMSPlugin\Registry\BlockTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ConditionKindRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;

/**
 * Applies a change set to a working copy, all or nothing: to its block tree, and to its translations' values, shown
 * switches and translation state. The values themselves aren't validated, because invalid content saves: publish validates. They're
 * sanitised by field types that sanitise, such as rich text, and so are the parameter values of visibility conditions
 *
 * @internal
 */
final readonly class ChangeSetApplier
{
    public function __construct(
        private BlockTypeRegistryInterface $blockTypes,
        private FieldHandlerLocator $fieldHandlers,
        private ContentTypeRegistryInterface $contentTypes,
        private ConditionKindRegistryInterface $conditionKinds,
    ) {
    }

    /**
     * @throws InvalidChangeSetException
     */
    public function apply(ChangeSet $changeSet, EntryInterface $workingCopy): void
    {
        $contentType = $this->contentTypes->getByEntry($workingCopy);
        // A copy, so nothing changes until every change is known to apply
        $tree = clone $workingCopy->getBlockTree();
        /** @var list<array{string, \Closure(EntryTranslationInterface): void}> $translationChanges the locale, and the change to its translation */
        $translationChanges = [];

        foreach ($changeSet->changes as $change) {
            match (true) {
                $change instanceof AddBlock => $this->addBlock($change, $tree),
                $change instanceof MoveBlock => $this->moveBlock($change, $tree),
                $change instanceof RemoveBlock => $this->removeBlock($change, $tree),
                $change instanceof SetValue => $this->setValue($change, $tree),
                $change instanceof SetStyle => $this->setStyle($change, $tree),
                $change instanceof SetConditions => $this->setConditions($change, $tree),
                $change instanceof SetEntryValue => $translationChanges[] = self::entryValue($change, $contentType),
                $change instanceof SetShown => $translationChanges[] = [$change->locale, static fn (EntryTranslationInterface $translation) => $translation->setShown($change->shown)],
                $change instanceof SetTranslated => $this->setTranslated($change, $tree),
                $change instanceof SetEntryTranslated => $translationChanges[] = self::entryTranslated($change, $contentType),
                default => throw new InvalidChangeSetException(sprintf('Unknown change %s', $change::class)),
            };
        }

        self::assertUniqueKeys($tree);

        $workingCopy->setBlockTree($tree);
        foreach ($translationChanges as [$locale, $changeTranslation]) {
            $changeTranslation(self::translation($workingCopy, $locale, $contentType));
        }
    }

    /**
     * The locale of an entry value and the change to its translation, once the value is known to fit
     *
     * @return array{string, \Closure(EntryTranslationInterface): void}
     */
    private static function entryValue(SetEntryValue $change, ContentTypeMetadata $contentType): array
    {
        self::assertEntryField($change->field, $contentType);

        if (null === $change->locale) {
            throw new InvalidChangeSetException(sprintf('The entry field "%s" is translatable, so a value needs a locale', $change->field));
        }

        if (null !== $change->value && !is_string($change->value)) {
            throw new InvalidChangeSetException(sprintf('The entry field "%s" must be a string or null', $change->field));
        }

        // An empty value is no value
        $value = '' === $change->value ? null : $change->value;

        return [$change->locale, 'slug' === $change->field
            ? static fn (EntryTranslationInterface $translation) => $translation->setSlug($value)
            : static fn (EntryTranslationInterface $translation) => $translation->setTitle($value)];
    }

    /**
     * The locale of where an entry value was translated from and the change to its translation, once the field is
     * known to be one of the entry fields
     *
     * @return array{string, \Closure(EntryTranslationInterface): void}
     */
    private static function entryTranslated(SetEntryTranslated $change, ContentTypeMetadata $contentType): array
    {
        self::assertEntryField($change->field, $contentType);

        return [$change->locale, static function (EntryTranslationInterface $translation) use ($change): void {
            $translated = $translation->getTranslated();
            if (null === $change->translated) {
                unset($translated[$change->field]);
            } else {
                $translated[$change->field] = $change->translated;
            }

            $translation->setTranslated($translated);
        }];
    }

    /**
     * The entry fields are a translation's title, and its slug for a content type with its own URL. The content type's
     * own fields come with #265
     */
    private static function assertEntryField(string $field, ContentTypeMetadata $contentType): void
    {
        if ('title' !== $field && !('slug' === $field && $contentType->hasOwnUrl())) {
            throw new InvalidChangeSetException(sprintf('The content type "%s" has no entry field "%s"', $contentType->code, $field));
        }
    }

    /**
     * The working copy's translation in the locale. Setting something in a locale it has no translation in adds one,
     * which starts shown before the entry's first publish, and held back after it.
     * See "Held-back translations" in docs/spec/publishing.md
     */
    private static function translation(EntryInterface $workingCopy, string $localeCode, ContentTypeMetadata $contentType): EntryTranslationInterface
    {
        $translation = $workingCopy->getTranslations()->get($localeCode);
        if ($translation instanceof EntryTranslationInterface) {
            return $translation;
        }

        $translation = $contentType->createTranslation($localeCode);
        $translation->setShown(!$workingCopy->isPublished());
        $workingCopy->addTranslation($translation);

        return $translation;
    }

    /**
     * Every change finds its block by key, so no two blocks in a tree may share one: a duplicated block gets new keys,
     * for itself and every block inside it
     */
    private static function assertUniqueKeys(StoredTree $tree): void
    {
        $shared = array_keys(array_filter(array_count_values($tree->keys()), static fn (int $count): bool => $count > 1));
        if ([] !== $shared) {
            throw new InvalidChangeSetException(sprintf('The tree has more than one block with the key %s', implode(', ', $shared)));
        }
    }

    /**
     * The nesting rules aren't checked here: the builder refuses a drop or an insert that breaks them,
     * and data that breaks them anyway saves and is flagged. The builder only adds block types the code has, but a copy
     * of a block can hold blocks of a type the code no longer has, which are kept as they are
     */
    private function addBlock(AddBlock $change, StoredTree $tree): void
    {
        $this->blockType($change->block);

        $taken = array_intersect($change->block->keys(), $tree->keys());
        if ([] !== $taken) {
            throw new InvalidChangeSetException(sprintf('The tree already has a block with the key %s', implode(', ', $taken)));
        }

        $this->sanitizeBlock($change->block);
        self::insert($tree, $change->block, $change->index, $change->parent, $change->after);
    }

    private function moveBlock(MoveBlock $change, StoredTree $tree): void
    {
        $block = $tree->findBlock($change->key) ?? throw new InvalidChangeSetException(sprintf('The tree has no block with the key %s', $change->key));

        if (null !== $change->parent && in_array($change->parent, $block->keys(), true)) {
            throw new InvalidChangeSetException(sprintf('The block %s can\'t move inside itself', $change->key));
        }

        $tree->removeBlock($change->key);
        self::insert($tree, $block, $change->index, $change->parent, $change->after);
    }

    /**
     * Inserts the block right after the block $after when the list has it, and otherwise at the index
     */
    private static function insert(StoredTree $tree, StoredBlock $block, int $index, ?string $parent, ?string $after): void
    {
        $list = null === $parent ? $tree->blocks : ($tree->findBlock($parent)->children ?? []);
        foreach ($list as $i => $sibling) {
            if ($sibling->key === $after) {
                $index = $i + 1;
            }
        }

        if (!$tree->insertBlock($block, $index, $parent)) {
            // Only a parent the tree doesn't have refuses an insert
            throw new InvalidChangeSetException(sprintf('The tree has no block with the key %s', $parent));
        }
    }

    private function removeBlock(RemoveBlock $change, StoredTree $tree): void
    {
        if (!$tree->removeBlock($change->key)) {
            throw new InvalidChangeSetException(sprintf('The tree has no block with the key %s', $change->key));
        }
    }

    private function setValue(SetValue $change, StoredTree $tree): void
    {
        $block = $tree->findBlock($change->key) ?? throw new InvalidChangeSetException(sprintf('The tree has no block with the key %s', $change->key));
        $field = $this->blockType($block)->fields[$change->field] ?? throw new InvalidChangeSetException(sprintf('The block type "%s" has no field "%s"', $block->type, $change->field));

        if ($field->translatable && null === $change->locale) {
            throw new InvalidChangeSetException(sprintf('The field "%s" is translatable, so a value needs a locale', $change->field));
        }

        if (!$field->translatable && null !== $change->locale) {
            throw new InvalidChangeSetException(sprintf('The field "%s" is shared by all translations, so a value takes no locale', $change->field));
        }

        $block->setValue($change->field, $change->locale, null === $change->value ? null : $this->sanitize($field, $change->value));
    }

    private function setTranslated(SetTranslated $change, StoredTree $tree): void
    {
        $block = $tree->findBlock($change->key) ?? throw new InvalidChangeSetException(sprintf('The tree has no block with the key %s', $change->key));
        $field = $this->blockType($block)->fields[$change->field] ?? throw new InvalidChangeSetException(sprintf('The block type "%s" has no field "%s"', $block->type, $change->field));

        if (!$field->translatable) {
            throw new InvalidChangeSetException(sprintf('The field "%s" is shared by all translations, so it has no translation state', $change->field));
        }

        $block->setTranslated($change->field, $change->locale, $change->translated);
    }

    private function setStyle(SetStyle $change, StoredTree $tree): void
    {
        $block = $tree->findBlock($change->key) ?? throw new InvalidChangeSetException(sprintf('The tree has no block with the key %s', $change->key));
        $blockType = $this->blockType($block);

        if (null !== $change->style && !isset($blockType->styles[$change->style])) {
            throw new InvalidChangeSetException(sprintf('The block type "%s" has no block style "%s"', $block->type, $change->style));
        }

        $block->style = $change->style;
    }

    /**
     * A block of any type can carry conditions, also one of a type the code no longer has. The parameter values of a
     * kind the code has are sanitised by their field types, and a condition of a kind the code no longer has is kept as
     * it is, because the builder sends a block's conditions whole
     */
    private function setConditions(SetConditions $change, StoredTree $tree): void
    {
        $block = $tree->findBlock($change->key) ?? throw new InvalidChangeSetException(sprintf('The tree has no block with the key %s', $change->key));

        $block->conditions = array_map(function (array $condition): array {
            if (!$this->conditionKinds->has($condition['kind'])) {
                return $condition;
            }

            $fields = $this->conditionKinds->get($condition['kind'])->fields;
            foreach ($condition['params'] as $name => $value) {
                $field = $fields[$name] ?? null;
                if (null !== $field && null !== $value) {
                    $condition['params'][$name] = $this->sanitize($field, $value);
                }
            }

            return $condition;
        }, $change->conditions);
    }

    /**
     * Sanitises the values of a block and the blocks inside it. A block of a type the code no longer has is kept as it
     * is, with what's inside it
     */
    private function sanitizeBlock(StoredBlock $block): void
    {
        if (!$this->blockTypes->has($block->type)) {
            return;
        }

        $fields = $this->blockTypes->get($block->type)->fields;

        foreach ($block->fields as $name => $value) {
            $field = $fields[$name] ?? null;
            if (null === $field) {
                // A value the block type no longer has is ignored, and dropped on a later save
                continue;
            }

            if (!$field->translatable) {
                $block->fields[$name] = $this->sanitize($field, $value);
            } elseif (is_array($value)) {
                $block->fields[$name] = array_map(fn (mixed $localized): mixed => $this->sanitize($field, $localized), $value);
            }
        }

        foreach ($block->children as $child) {
            $this->sanitizeBlock($child);
        }
    }

    private function sanitize(FieldMetadata $field, mixed $value): mixed
    {
        $handler = $this->fieldHandlers->get($field->field);

        return $handler instanceof SanitizingFieldHandlerInterface ? $handler->sanitize($field->field, $value) : $value;
    }

    /**
     * The block's own type, which a change to the block needs. The blocks inside it can be of any type
     */
    private function blockType(StoredBlock $block): BlockTypeMetadata
    {
        if (!$this->blockTypes->has($block->type)) {
            throw new InvalidChangeSetException(sprintf('There is no block type with the code "%s"', $block->type));
        }

        return $this->blockTypes->get($block->type);
    }
}
