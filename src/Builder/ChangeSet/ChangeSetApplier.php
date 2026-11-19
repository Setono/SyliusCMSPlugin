<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

use Setono\SyliusCMSPlugin\Binding\BoundValue;
use Setono\SyliusCMSPlugin\Binding\StoredBinding;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\LinkLocaleParts;
use Setono\SyliusCMSPlugin\Field\SanitizingFieldHandlerInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;
use Setono\SyliusCMSPlugin\Registry\BlockTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ConditionKindRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeFields;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\EntryFields;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistryInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;

/**
 * Applies a change set to a working copy, all or nothing: to its block tree and its layout, to its shared entry fields,
 * the content type's own included, and to its translations' values, shown switches and translation state. The values
 * themselves aren't validated, because invalid content saves: publish validates. They're sanitised by field types that
 * sanitise, such as rich text, and so are the parameter values of visibility conditions
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
        private LayoutRegistryInterface $layouts,
        private ContentTypeFields $contentTypeFields = new ContentTypeFields(new FieldReader()),
    ) {
    }

    /**
     * @param list<string>|null $locales the locales a translation may be added in, the locales of the entry's channels,
     *                                   or null for any. A translation the entry has may be changed in any locale
     *
     * @throws InvalidChangeSetException
     */
    public function apply(ChangeSet $changeSet, EntryInterface $workingCopy, ?array $locales = null): void
    {
        $contentType = $this->contentTypes->getByEntry($workingCopy);
        $sharedFields = $this->contentTypeFields->shared($contentType);
        $translatableFields = $this->contentTypeFields->translatable($contentType);
        // A copy, so nothing changes until every change is known to apply
        $tree = clone $workingCopy->getBlockTree();
        /** @var list<array{string, \Closure(EntryTranslationInterface): void}> $translationChanges the locale, and the change to its translation */
        $translationChanges = [];
        /** @var list<\Closure(EntryInterface): void> $entryChanges changes to the entry's shared fields */
        $entryChanges = [];

        foreach ($changeSet->changes as $change) {
            match (true) {
                $change instanceof AddBlock => $this->addBlock($change, $tree),
                $change instanceof MoveBlock => $this->moveBlock($change, $tree),
                $change instanceof RemoveBlock => $this->removeBlock($change, $tree),
                $change instanceof SetValue => $this->setValue($change, $tree),
                $change instanceof SetStyle => $this->setStyle($change, $tree),
                $change instanceof SetConditions => $this->setConditions($change, $tree),
                $change instanceof SetEntryValue && in_array($change->field, EntryFields::shared($contentType), true) => $entryChanges[] = self::sharedEntryValue($change),
                $change instanceof SetEntryValue && isset($sharedFields[$change->field]) => $entryChanges[] = $this->ownSharedValue($change, $sharedFields[$change->field]),
                $change instanceof SetEntryValue && isset($translatableFields[$change->field]) => $translationChanges[] = $this->ownTranslatableValue($change, $translatableFields[$change->field]),
                $change instanceof SetEntryValue => $translationChanges[] = self::entryValue($change, $contentType),
                $change instanceof SetShown => $translationChanges[] = [$change->locale, static fn (EntryTranslationInterface $translation) => $translation->setShown($change->shown)],
                $change instanceof SetTranslated => $this->setTranslated($change, $tree),
                $change instanceof SetEntryTranslated => $translationChanges[] = self::entryTranslated($change, $contentType, $sharedFields, $translatableFields),
                $change instanceof SetLayout => $this->setLayout($change, $tree, $contentType),
                $change instanceof SetLayoutValue => $this->setLayoutValue($change, $tree, $contentType),
                $change instanceof SetLayoutTranslated => $this->setLayoutTranslated($change, $tree, $contentType),
                default => throw new InvalidChangeSetException(sprintf('Unknown change %s', $change::class)),
            };
        }

        self::assertUniqueKeys($tree);
        foreach ($translationChanges as [$locale]) {
            if (null !== $locales && !$workingCopy->getTranslations()->containsKey($locale) && !in_array($locale, $locales, true)) {
                throw new InvalidChangeSetException(sprintf('The entry\'s channels have no locale "%s" to add a translation in', $locale));
            }
        }

        $workingCopy->setBlockTree($tree);
        foreach ($entryChanges as $changeEntry) {
            $changeEntry($workingCopy);
        }

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

        return [$change->locale, static fn (EntryTranslationInterface $translation) => EntryFields::write($translation, $change->field, $value)];
    }

    /**
     * The change to a field of the entry shared by all translations, such as "Hide from search engines" or a snippet's
     * declared context, once the value is known to fit. Removing the value gives it its default: off, or no context keys
     *
     * @return \Closure(EntryInterface): void
     */
    private static function sharedEntryValue(SetEntryValue $change): \Closure
    {
        if (null !== $change->locale) {
            throw new InvalidChangeSetException(sprintf('The entry field "%s" is shared by all translations, so a value takes no locale', $change->field));
        }

        try {
            $value = EntryFields::sharedValue($change->field, $change->value);
        } catch (\InvalidArgumentException $e) {
            throw new InvalidChangeSetException(sprintf('The entry field "%s" %s', $change->field, $e->getMessage()), previous: $e);
        }

        return static fn (EntryInterface $entry) => EntryFields::writeShared($entry, $change->field, $value);
    }

    /**
     * The change to one of the content type's own fields shared by all translations, sanitised by its field type. An
     * empty value removes it, which gives the property its default
     *
     * @return \Closure(EntryInterface): void
     */
    private function ownSharedValue(SetEntryValue $change, FieldMetadata $field): \Closure
    {
        if (null !== $change->locale) {
            throw new InvalidChangeSetException(sprintf('The entry field "%s" is shared by all translations, so a value takes no locale', $change->field));
        }

        $value = $this->ownValue($field, $change->value);

        return fn (EntryInterface $entry) => $this->contentTypeFields->write($entry, $change->field, $value);
    }

    /**
     * The locale of a value of one of the content type's own translatable fields and the change to its translation,
     * sanitised by its field type
     *
     * @return array{string, \Closure(EntryTranslationInterface): void}
     */
    private function ownTranslatableValue(SetEntryValue $change, FieldMetadata $field): array
    {
        if (null === $change->locale) {
            throw new InvalidChangeSetException(sprintf('The entry field "%s" is translatable, so a value needs a locale', $change->field));
        }

        $value = $this->ownValue($field, $change->value);

        return [$change->locale, fn (EntryTranslationInterface $translation) => $this->contentTypeFields->write($translation, $change->field, $value)];
    }

    /**
     * A value of one of the content type's own fields, sanitised, or null for a missing one
     */
    private function ownValue(FieldMetadata $field, mixed $value): mixed
    {
        return null === $value || '' === $value || [] === $value ? null : $this->sanitize($field, $value);
    }

    /**
     * The locale of where an entry value was translated from and the change to its translation, once the field is
     * known to be one of the translatable entry fields
     *
     * @param array<string, FieldMetadata> $sharedFields the content type's own shared fields
     * @param array<string, FieldMetadata> $translatableFields the content type's own translatable fields
     *
     * @return array{string, \Closure(EntryTranslationInterface): void}
     */
    private static function entryTranslated(SetEntryTranslated $change, ContentTypeMetadata $contentType, array $sharedFields, array $translatableFields): array
    {
        if (in_array($change->field, EntryFields::shared($contentType), true) || isset($sharedFields[$change->field])) {
            throw new InvalidChangeSetException(sprintf('The entry field "%s" is shared by all translations, so it has no translation state', $change->field));
        }

        if (!isset($translatableFields[$change->field])) {
            self::assertEntryField($change->field, $contentType);
        }

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
     * The translatable base fields are a translation's title, and its slug, meta title and meta description for a
     * content type with its own URL
     */
    private static function assertEntryField(string $field, ContentTypeMetadata $contentType): void
    {
        if (!in_array($field, EntryFields::translatable($contentType), true)) {
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

        [$locale, $value] = $this->valueToSet($field, $change->field, $change->locale, $change->value, $block->fields[$change->field] ?? null);
        $block->setValue($change->field, $locale, $value);
    }

    private function setTranslated(SetTranslated $change, StoredTree $tree): void
    {
        $block = $tree->findBlock($change->key) ?? throw new InvalidChangeSetException(sprintf('The tree has no block with the key %s', $change->key));
        $field = $this->blockType($block)->fields[$change->field] ?? throw new InvalidChangeSetException(sprintf('The block type "%s" has no field "%s"', $block->type, $change->field));

        if (!$field->translatable && !LinkLocaleParts::areKeptBy($field->field)) {
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
     * Switches the layout to the one the builder made, with the values that carried over. A layout the code doesn't
     * have, such as the removed one that undoing a switch from it brings back, is kept as it is
     */
    private function setLayout(SetLayout $change, StoredTree $tree, ContentTypeMetadata $contentType): void
    {
        if (!$contentType->hasOwnUrl()) {
            throw new InvalidChangeSetException(sprintf('The content type "%s" has no layouts', $contentType->code));
        }

        $layout = clone $change->layout;
        if ($this->layouts->has($contentType->code, $layout->code)) {
            $fields = $this->layouts->get($contentType->code, $layout->code)->fields;
            foreach ($layout->fields as $name => $value) {
                $field = $fields[$name] ?? throw new InvalidChangeSetException(sprintf('The layout "%s" has no field "%s"', $layout->code, $name));
                $layout->fields[$name] = $this->sanitizeStored($field, $value);
            }
        }

        $tree->layout = $layout;
    }

    private function setLayoutValue(SetLayoutValue $change, StoredTree $tree, ContentTypeMetadata $contentType): void
    {
        [$layout, $field] = $this->layoutField($change->layout, $change->field, $tree, $contentType);

        [$locale, $value] = $this->valueToSet($field, $change->field, $change->locale, $change->value, $layout->fields[$change->field] ?? null);
        $layout->setValue($change->field, $locale, $value);
    }

    private function setLayoutTranslated(SetLayoutTranslated $change, StoredTree $tree, ContentTypeMetadata $contentType): void
    {
        [$layout, $field] = $this->layoutField($change->layout, $change->field, $tree, $contentType);

        if (!$field->translatable && !LinkLocaleParts::areKeptBy($field->field)) {
            throw new InvalidChangeSetException(sprintf('The field "%s" is shared by all translations, so it has no translation state', $change->field));
        }

        $layout->setTranslated($change->field, $change->locale, $change->translated);
    }

    /**
     * The tree's layout and the field a change to it is about. The change names the layout it was made in, which must
     * be the tree's still
     *
     * @return array{StoredLayout, FieldMetadata}
     */
    private function layoutField(string $code, string $name, StoredTree $tree, ContentTypeMetadata $contentType): array
    {
        $layout = $tree->layout;
        if (null === $layout || $layout->code !== $code) {
            throw new InvalidChangeSetException(sprintf('The tree has no layout "%s", so it can\'t take the change to its field "%s"', $code, $name));
        }

        if (!$this->layouts->has($contentType->code, $code)) {
            throw new InvalidChangeSetException(sprintf('The content type "%s" has no layout with the code "%s"', $contentType->code, $code));
        }

        $field = $this->layouts->get($contentType->code, $code)->fields[$name] ?? throw new InvalidChangeSetException(sprintf('The layout "%s" has no field "%s"', $code, $name));

        return [$layout, $field];
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

            $block->fields[$name] = $this->sanitizeStored($field, $value);
        }

        foreach ($block->children as $child) {
            $this->sanitizeBlock($child);
        }
    }

    /**
     * Sanitises a field's value as it's stored: a shared field's one value, or a translatable field's value in each locale
     */
    private function sanitizeStored(FieldMetadata $field, mixed $value): mixed
    {
        if (BoundValue::isBound($field, $value)) {
            return $this->sanitizeBinding($field, $value);
        }

        if (!$field->translatable) {
            return $this->sanitize($field, $value);
        }

        return is_array($value) ? array_map(fn (mixed $localized): mixed => $this->sanitize($field, $localized), $value) : $value;
    }

    /**
     * The locale and the sanitised value a change sets in a field. A Link that keeps its label and URL per locale
     * inside its one value takes those parts in a locale, as a translator writes them, and they're set into the link
     *
     * @param mixed $stored what the field stores now
     *
     * @return array{string|null, mixed}
     */
    private function valueToSet(FieldMetadata $field, string $name, ?string $locale, mixed $value, mixed $stored): array
    {
        if (null !== $locale && LinkLocaleParts::areKeptBy($field->field)) {
            try {
                return [null, $this->sanitize($field, LinkLocaleParts::with($stored, $locale, $value))];
            } catch (\InvalidArgumentException $e) {
                throw new InvalidChangeSetException(sprintf('The parts of the link "%s" in %s: %s', $name, $locale, $e->getMessage()), previous: $e);
            }
        }

        // A binding is the whole value of a field that can be bound, which takes it, or null to unbind it, with no locale
        if (null === $locale && $field->bindsWholeValue() && (null === $value || StoredBinding::isBinding($value))) {
            return [null, null === $value ? null : $this->sanitizeBinding($field, $value)];
        }

        if ($field->translatable && null === $locale) {
            throw new InvalidChangeSetException(sprintf('The field "%s" is translatable, so a value needs a locale', $name));
        }

        if (!$field->translatable && null !== $locale) {
            throw new InvalidChangeSetException(sprintf('The field "%s" is shared by all translations, so a value takes no locale', $name));
        }

        return [$locale, null === $value ? null : $this->sanitize($field, $value)];
    }

    /**
     * A binding with its fallback sanitised as the field's value would be. One of another shape is saved as it is,
     * because invalid content saves: publish refuses it
     */
    private function sanitizeBinding(FieldMetadata $field, mixed $value): mixed
    {
        $binding = BoundValue::of($field, $value);
        if (null === $binding || null === $binding->fallback) {
            return $value;
        }

        $fallback = $field->translatable && is_array($binding->fallback)
            ? array_map(fn (mixed $localized): mixed => $this->sanitize($field, $localized), $binding->fallback)
            : $this->sanitize($field, $binding->fallback);

        return (new StoredBinding($binding->property, $binding->params, $fallback))->toArray();
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
