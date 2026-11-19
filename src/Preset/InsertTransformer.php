<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

use Setono\SyliusCMSPlugin\Field\LinkLocaleParts;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;
use Setono\SyliusCMSPlugin\Registry\LayoutMetadata;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;
use Setono\SyliusCMSPlugin\Upgrade\NewerVersion;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Uid\Uuid;

/**
 * Makes a preset's blocks ready to insert into an entry, code presets and editor presets alike. Inserting copies
 * everything: the nesting, every value with its translation state, conditions, styles, source settings, bindings and
 * references. On the way:
 *
 * - the upgraders run, and a block saved with a newer version than the code's refuses the insert
 * - the blocks of a type the code no longer has, or has disabled, are left out with what's inside them, with a notice
 * - each block gets a new key
 * - translation keys resolve in each of the entry's locales that has a translation, and the others stay empty. A rich
 *   text field's translation is HTML
 * - the values and translation state of locales the entry doesn't have are dropped
 *
 * Other problems are copied as they are, and flagged like any invalid block once inserted. See "What inserting copies"
 * and "Presets with invalid blocks" in docs/spec/layouts-and-presets.md
 */
final readonly class InsertTransformer
{
    public function __construct(
        private StoredDataUpgraderInterface $upgrader,
        private BlockTypeRegistryInterface $blockTypes,
        private TranslationKeyResolverInterface $translationKeys,
        private TranslationKeyValues $translationKeyValues,
    ) {
    }

    /**
     * @param list<string> $locales the locales of the entry the blocks are inserted into
     */
    public function transform(Preset $preset, array $locales): BlocksToInsert
    {
        $serializer = new StoredTreeSerializer();
        /** @var list<mixed> $stored */
        $stored = CanonicalJson::decode(CanonicalJson::encode(array_map($serializer->normalizeBlock(...), $preset->blocks)));

        $upgraded = $this->upgrader->upgradeBlocks($stored);
        if ([] !== $upgraded->newer) {
            return new BlocksToInsert([], array_map(self::newerVersionNotice(...), $upgraded->newer), true);
        }

        /** @var array<string, TranslatableMessage> $notices */
        $notices = [];
        $blocks = $this->copy(array_map($serializer->denormalizeBlock(...), array_values($upgraded->data)), $locales, $notices);

        return new BlocksToInsert($blocks, array_values($notices));
    }

    /**
     * A starting point's layout made ready for a new entry, its field values as a block's are on insert: translation
     * keys resolve in each of the entry's locales that has a translation, and the values and translation state of the
     * locales the entry doesn't have are dropped. The layout must be upgraded already
     *
     * @param list<string> $locales the locales of the entry the layout is for
     */
    public function transformLayout(StoredLayout $layout, LayoutMetadata $metadata, array $locales): StoredLayout
    {
        return new StoredLayout($layout->code, $layout->version, $this->fields($metadata->fields, $layout->fields, $locales), self::translated($layout->translated, $locales));
    }

    /**
     * Why inserting refuses what was saved with a newer version than the code's
     */
    public static function newerVersionNotice(NewerVersion $newer): TranslatableMessage
    {
        return new TranslatableMessage('setono_sylius_cms.preset.newer_version', [
            '%type%' => $newer->code,
            '%version%' => $newer->version,
            '%code_version%' => $newer->codeVersion,
        ]);
    }

    /**
     * @param list<StoredBlock> $blocks
     * @param list<string> $locales
     * @param array<string, TranslatableMessage> $notices one per block type left out, by type
     *
     * @return list<StoredBlock>
     */
    private function copy(array $blocks, array $locales, array &$notices): array
    {
        $copies = [];
        foreach ($blocks as $block) {
            if (!$this->blockTypes->has($block->type)) {
                $notices[$block->type] ??= new TranslatableMessage('setono_sylius_cms.preset.removed_block_type', ['%type%' => $block->type]);

                continue;
            }

            $blockType = $this->blockTypes->get($block->type);
            if ($blockType->disabled) {
                $notices[$block->type] ??= new TranslatableMessage('setono_sylius_cms.preset.disabled_block_type', ['%block%' => new TranslatableMessage($blockType->label)]);

                continue;
            }

            $copies[] = new StoredBlock(
                Uuid::v7()->toRfc4122(),
                $block->type,
                $block->version,
                $block->style,
                $this->fields($blockType->fields, $block->fields, $locales),
                self::translated($block->translated, $locales),
                $block->conditions,
                $this->copy($block->children, $locales, $notices),
            );
        }

        return $copies;
    }

    /**
     * @param array<string, FieldMetadata> $metadata the fields of the block type or the layout
     * @param array<string, mixed> $values
     * @param list<string> $locales
     *
     * @return array<string, mixed>
     */
    private function fields(array $metadata, array $values, array $locales): array
    {
        $fields = [];
        foreach ($values as $name => $value) {
            $field = $metadata[$name] ?? null;
            if (null !== $field) {
                $value = self::inLocales($field, $this->translationKeyValues->resolve($field, $value, $locales, $this->translationKeys->resolve(...)), $locales);
            }

            // A field left without a value in any locale has none
            if (!$value instanceof \stdClass) {
                $fields[$name] = $value;
            }
        }

        return $fields;
    }

    /**
     * The value without the locales the entry doesn't have: a translatable field's locale map, the label and ref of a
     * Link that keeps them per locale, and a bound translatable field's fallback. {} when no locale is left
     *
     * @param list<string> $locales
     */
    private static function inLocales(FieldMetadata $field, mixed $value, array $locales): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (LinkLocaleParts::areKeptBy($field->field)) {
            foreach (['label', 'ref'] as $part) {
                if (self::isLocaleMap($value[$part] ?? null)) {
                    $value[$part] = self::onlyLocales($value[$part], $locales);
                }
            }

            return $value;
        }

        if (!$field->translatable) {
            return $value;
        }

        if (array_key_exists('$bind', $value)) {
            if (self::isLocaleMap($value['$fallback'] ?? null)) {
                $value['$fallback'] = self::onlyLocales($value['$fallback'], $locales);
            }

            return $value;
        }

        return self::isLocaleMap($value) ? self::onlyLocales($value, $locales) : $value;
    }

    /**
     * @param array<string, mixed> $translated field name => locale => translation state
     * @param list<string> $locales
     *
     * @return array<string, mixed>
     */
    private static function translated(array $translated, array $locales): array
    {
        $kept = [];
        foreach ($translated as $field => $byLocale) {
            $byLocale = $byLocale instanceof \stdClass ? get_object_vars($byLocale) : $byLocale;
            if (!is_array($byLocale)) {
                continue;
            }

            $byLocale = array_intersect_key($byLocale, array_flip($locales));
            if ([] !== $byLocale) {
                $kept[$field] = $byLocale;
            }
        }

        return $kept;
    }

    /**
     * @param array<array-key, mixed> $map
     * @param list<string> $locales
     *
     * @return array<array-key, mixed>|\stdClass
     */
    private static function onlyLocales(array $map, array $locales): array|\stdClass
    {
        $map = array_intersect_key($map, array_flip($locales));

        return [] === $map ? new \stdClass() : $map;
    }

    /**
     * A map of locale codes to values. A special value, such as a binding or a source, isn't one
     *
     * @phpstan-assert-if-true non-empty-array<string, mixed> $value
     */
    private static function isLocaleMap(mixed $value): bool
    {
        if (!is_array($value) || [] === $value || array_is_list($value)) {
            return false;
        }

        foreach (array_keys($value) as $key) {
            if (str_starts_with((string) $key, '$')) {
                return false;
            }
        }

        return true;
    }
}
