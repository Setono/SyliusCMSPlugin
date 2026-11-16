<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Translation;

use Setono\SyliusCMSPlugin\Condition\Kind\LocaleKind;
use Setono\SyliusCMSPlugin\Field\Field;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\EntryFields;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistryInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * The translatable values of a working copy in a locale, with their translation state: its translations' own fields,
 * its layout's fields and its blocks' fields, at any depth, but those of a block a locale condition hides in the locale,
 * which nobody reads there. The one place the rules are, which TranslationState gives
 * a translator, and the completeness counts and outdated flags of the admin read. The builder has the same rules
 * (assets/builder/src/translationState.ts). See "Completeness and outdated flags" in docs/spec/localization.md
 *
 * @internal
 */
final readonly class TranslatableValues
{
    public function __construct(
        private ContentTypeRegistryInterface $contentTypes,
        private BlockTypeRegistryInterface $blockTypes,
        private LayoutRegistryInterface $layouts,
        private FallbackValues $fallbackValues,
        private FallbackChainInterface $fallbackChain,
    ) {
    }

    /**
     * Every translatable value that some locale has a value for, in order: the entry's own fields, the layout's, then
     * the blocks' depth first. A value no locale has is left out, because there's nothing to translate
     *
     * @return list<TranslatableValue>
     */
    public function of(EntryInterface $workingCopy, string $localeCode): array
    {
        $values = [];

        $contentType = $this->contentTypes->getByEntry($workingCopy);
        /** @var array<string, EntryTranslationInterface> $translations Translations are indexed by locale */
        $translations = $workingCopy->getTranslations()->toArray();
        foreach (EntryFields::translatable($contentType) as $field) {
            $this->add(
                $values,
                sprintf('entry.%s', $field),
                null,
                array_map(static fn (EntryTranslationInterface $translation): ?string => EntryFields::read($translation, $field), $translations),
                array_map(static fn (EntryTranslationInterface $translation): ?array => $translation->getTranslated()[$field] ?? null, $translations),
                $localeCode,
            );
        }

        $tree = $workingCopy->getBlockTree();
        $layout = $tree->layout;
        // A layout that was removed or disabled isn't rendered, so there's nothing in it to translate
        if (null !== $layout && $this->layouts->has($contentType->code, $layout->code) && !$this->layouts->get($contentType->code, $layout->code)->disabled) {
            foreach ($this->translatable($this->layouts->get($contentType->code, $layout->code)->fields) as $name => $field) {
                $this->add($values, sprintf('layout.%s', $name), $field, $layout->fields[$name] ?? null, $layout->translated[$name] ?? null, $localeCode);
            }
        }

        foreach ($tree->blocks as $block) {
            $this->blockValues($block, $localeCode, $values);
        }

        return $values;
    }

    public function completeness(EntryInterface $workingCopy, string $localeCode): Completeness
    {
        return Completeness::of($localeCode, $this->of($workingCopy, $localeCode));
    }

    /**
     * @param list<TranslatableValue> $values
     */
    private function blockValues(StoredBlock $block, string $localeCode, array &$values): void
    {
        // Nobody reads a block in a locale it's hidden in, nor what's inside it
        if (LocaleKind::hidesIn($block->conditions, $localeCode)) {
            return;
        }

        // A block of a type the code no longer has isn't rendered, so there's nothing in it to translate
        if ($this->blockTypes->has($block->type)) {
            foreach ($this->translatable($this->blockTypes->get($block->type)->fields) as $name => $field) {
                $this->add($values, sprintf('block.%s.%s', $block->key, $name), $field, $block->fields[$name] ?? null, $block->translated[$name] ?? null, $localeCode);
            }
        }

        foreach ($block->children as $child) {
            $this->blockValues($child, $localeCode, $values);
        }
    }

    /**
     * @param array<string, FieldMetadata> $fields
     *
     * @return array<string, Field>
     */
    private function translatable(array $fields): array
    {
        return array_map(
            static fn (FieldMetadata $field): Field => $field->field,
            array_filter($fields, static fn (FieldMetadata $field): bool => $field->translatable),
        );
    }

    /**
     * Adds the value at the path to the values, unless no locale has a value there
     *
     * @param list<TranslatableValue> $values
     * @param Field|null $field the field, or null for an entry field, which is a string
     * @param mixed $stored what the field stores: a map from locale to value
     * @param mixed $translated where each locale's value was translated from
     */
    private function add(array &$values, string $path, ?Field $field, mixed $stored, mixed $translated, string $localeCode): void
    {
        $value = $this->value($path, $field, $stored, $translated, $localeCode);
        if (null !== $value) {
            $values[] = $value;
        }
    }

    private function value(string $path, ?Field $field, mixed $stored, mixed $translated, string $localeCode): ?TranslatableValue
    {
        /** @var array<string, mixed> $stored a map from locale code to value */
        $stored = is_array($stored) ? $stored : [];
        $value = $stored[$localeCode] ?? null;

        if ($this->isMissing($field, $value)) {
            $sourceLocaleCode = $this->untranslatedSource($field, $stored, $localeCode);

            return null === $sourceLocaleCode ? null : new TranslatableValue(
                $path,
                ValueState::Untranslated,
                $sourceLocaleCode,
                $stored[$sourceLocaleCode],
                Fingerprint::of($stored[$sourceLocaleCode]),
                null,
            );
        }

        $source = is_array($translated) ? $translated[$localeCode] ?? null : null;
        $sourceLocaleCode = is_array($source) && is_string($source['from'] ?? null) ? $source['from'] : null;
        if (null === $sourceLocaleCode) {
            return new TranslatableValue($path, ValueState::UpToDate, null, null, null, $value);
        }

        $sourceValue = $stored[$sourceLocaleCode] ?? null;
        if ($this->isMissing($field, $sourceValue)) {
            // A source with no value any more can't have changed into something to translate
            return new TranslatableValue($path, ValueState::UpToDate, $sourceLocaleCode, null, null, $value);
        }

        $sourceFingerprint = Fingerprint::of($sourceValue);

        return new TranslatableValue(
            $path,
            $sourceFingerprint === ($source['fingerprint'] ?? null) ? ValueState::UpToDate : ValueState::Outdated,
            $sourceLocaleCode,
            $sourceValue,
            $sourceFingerprint,
            $value,
        );
    }

    /**
     * The locale an untranslated value is translated from: the first its fallback chain reaches that has a value, or else
     * the first by code that has one. Null when no other locale has a value
     *
     * @param array<string, mixed> $stored locale code => value, which is missing in $localeCode
     */
    private function untranslatedSource(?Field $field, array $stored, string $localeCode): ?string
    {
        $others = [];
        foreach ($stored as $otherLocaleCode => $value) {
            if (!$this->isMissing($field, $value)) {
                $others[] = $otherLocaleCode;
            }
        }
        sort($others);

        foreach ($this->fallbackChain->localesFor($localeCode) as $chained) {
            if (in_array($chained, $others, true)) {
                return $chained;
            }
        }

        return $others[0] ?? null;
    }

    /**
     * A value that doesn't fit its field type counts as missing, so a locale that holds one has it untranslated, and a
     * value isn't translated from it
     */
    private function isMissing(?Field $field, mixed $value): bool
    {
        return null === $field ? null === $value || '' === $value : $this->fallbackValues->isMissing($field, $value) || !$this->fallbackValues->fits($field, $value);
    }
}
