<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder;

use Setono\SyliusCMSPlugin\Attribute\Style;
use Setono\SyliusCMSPlugin\Binding\BindingType;
use Setono\SyliusCMSPlugin\Context\EntryContext;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Media\MediaLimits;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Registry\AllowedBlockType;
use Setono\SyliusCMSPlugin\Registry\BindablePropertyMetadata;
use Setono\SyliusCMSPlugin\Registry\BindablePropertyRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\BlockTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ConditionKindMetadata;
use Setono\SyliusCMSPlugin\Registry\ConditionKindRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeFields;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ContextKeyMetadata;
use Setono\SyliusCMSPlugin\Registry\ContextKeyRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutMetadata;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\LinkTargetMetadata;
use Setono\SyliusCMSPlugin\Registry\LinkTargetRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\NestingRulesInterface;
use Setono\SyliusCMSPlugin\Registry\SlotMetadata;
use Setono\SyliusCMSPlugin\Registry\SlotRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\SourceMetadata;
use Setono\SyliusCMSPlugin\Registry\SourceRegistryInterface;
use Setono\SyliusCMSPlugin\Source\EntryContextAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What the builder's registry endpoint answers: the block types with their field schemas, nesting rules and block styles,
 * what the top level of each content type may hold, the layouts editors can pick for it with their field schemas, the
 * condition kinds with their parameters' field schemas, the link targets, the sources editors may pick with their parameters' field schemas, the context keys and the slots, the bindable properties with their parameters' field schemas, what each block type and
 * condition kind needs and what each content type provides, the effective upload limit with the MIME types
 * of each kind of media item, and the UI strings, translated in the current locale, which in the admin is the admin user's locale. The
 * shape is internal to the builder
 */
final readonly class RegistryExporter
{
    private const STRINGS_PREFIX = 'setono_sylius_cms.builder.';

    public function __construct(
        private BlockTypeRegistryInterface $blockTypes,
        private ContentTypeRegistryInterface $contentTypes,
        private NestingRulesInterface $nestingRules,
        private FieldHandlerLocator $fieldHandlers,
        private TranslatorInterface $translator,
        private UiStrings $uiStrings,
        private ConditionKindRegistryInterface $conditionKinds,
        private LayoutRegistryInterface $layouts,
        private MediaLimits $mediaLimits,
        private LinkTargetRegistryInterface $linkTargets,
        private SourceRegistryInterface $sources,
        private ContextKeyRegistryInterface $contextKeys,
        private SlotRegistryInterface $slots,
        private BindablePropertyRegistryInterface $bindableProperties,
        private ContentTypeFields $contentTypeFields = new ContentTypeFields(new FieldReader()),
    ) {
    }

    /**
     * @return array{blockTypes: list<array<string, mixed>>, contentTypes: list<array<string, mixed>>, conditionKinds: list<array<string, mixed>>, linkTargets: list<array{code: string, label: string, translatableRef: bool}>, sources: list<array<string, mixed>>, contextKeys: list<array{key: string, label: string}>, slots: list<array{identifier: string, label: string, provides: list<string>}>, bindableProperties: list<array<string, mixed>>, media: array{maxUploadSize: int, types: array<string, list<string>>}, strings: array<string, string>}
     */
    public function export(): array
    {
        return [
            'blockTypes' => array_map($this->blockType(...), $this->blockTypes->all()),
            // A disabled content type is hidden from the builder
            'contentTypes' => array_map($this->contentType(...), $this->contentTypes->enabled()),
            'conditionKinds' => array_map($this->conditionKind(...), $this->conditionKinds->all()),
            // In the order the link control offers them. A target whose ref is translatable, such as a URL, is typed per locale, and the others are picked with the search under their code
            'linkTargets' => array_map(fn (LinkTargetMetadata $target): array => [
                'code' => $target->code,
                'label' => $this->translator->trans($target->label),
                'translatableRef' => $target->translatableRef,
            ], $this->linkTargets->all()),
            // In the order the "Filled by" select offers them, after Hand-picked. Each list field's schema names those it offers
            'sources' => $this->sources(),
            // What a snippet's context is picked from: the keys, named by the pages that provide them, and the slots
            'contextKeys' => $this->contextKeys(),
            'slots' => array_map(fn (SlotMetadata $slot): array => [
                'identifier' => $slot->identifier,
                'label' => $this->translator->trans($slot->label),
                'provides' => $slot->provides,
            ], $this->slots->all()),
            // What the binding picker lists, in this order, for a field that accepts the type where the entry provides the needs
            'bindableProperties' => array_map(fn (BindablePropertyMetadata $property): array => [
                'binding' => $property->binding(),
                'label' => $this->translator->trans($property->label),
                'type' => $property->type->value,
                'needs' => $property->needs(),
                'fields' => $this->sharedFields($property->fields),
            ], $this->bindableProperties->all()),
            'media' => $this->mediaLimits->toArray(),
            'strings' => $this->uiStrings->withPrefix(self::STRINGS_PREFIX),
        ];
    }

    /**
     * What the page scope editor on a snippet's overview page needs: the condition kinds a page scope can hold, those
     * that read the host page, as export() has them, and the context keys they need. See "Page scopes" in
     * docs/spec/slots.md
     *
     * @return array{conditionKinds: list<array<string, mixed>>, contextKeys: list<array{key: string, label: string}>}
     */
    public function exportForPageScopes(): array
    {
        return [
            'conditionKinds' => array_values(array_map(
                $this->conditionKind(...),
                array_filter($this->conditionKinds->all(), static fn (ConditionKindMetadata $kind): bool => $kind->readsHostPage()),
            )),
            'contextKeys' => $this->contextKeys(),
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function contextKeys(): array
    {
        return array_map(fn (ContextKeyMetadata $key): array => ['key' => $key->key, 'label' => $this->translator->trans($key->label)], $this->contextKeys->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function blockType(BlockTypeMetadata $blockType): array
    {
        return [
            'code' => $blockType->code,
            'label' => $this->translator->trans($blockType->label),
            'version' => $blockType->version,
            'thumbnail' => $blockType->thumbnail,
            // The picker leaves a disabled block type out, but blocks of it already in the tree stay and can still be moved
            'disabled' => $blockType->disabled,
            'parents' => null === $blockType->parents ? null : array_map(fn (string $class): string => $this->blockTypes->getByClass($class)->code, $blockType->parents),
            'children' => self::allowed($this->nestingRules->children($blockType->code)),
            // The picker offers it only where the entry provides these context keys or the snippet declares them
            'needs' => $blockType->needs,
            // The style picker stores a style's code in the block's style; its class only matters to rendering
            'styles' => array_values(array_map(fn (Style $style): array => ['code' => $style->code, 'label' => $this->translator->trans($style->label)], $blockType->styles)),
            'fields' => $this->fields($blockType->fields),
        ];
    }

    /**
     * @param array<string, FieldMetadata> $fields
     *
     * @return list<array{name: string, schema: array<string, mixed>}>
     */
    private function fields(array $fields): array
    {
        return array_values(array_map(fn (FieldMetadata $field): array => ['name' => $field->name, 'schema' => $this->schema($field)], $fields));
    }

    /**
     * @return array<string, mixed>
     */
    private function contentType(ContentTypeMetadata $contentType): array
    {
        return [
            'code' => $contentType->code,
            'label' => $this->translator->trans($contentType->label),
            'blocks' => null === $contentType->blocks ? null : self::allowed($this->nestingRules->topLevel($contentType->code)),
            // null when it has no URL of its own, so no slug
            'prefix' => $contentType->prefix,
            // The code of its collection's category content type, whose entries From a collection's categories picker searches
            'categoryType' => $contentType->categoryType,
            // Whether its entries can be a channel's homepage, which only pages can, so the settings offer "Homepage of"
            'homepage' => is_a($contentType->modelClass, PageInterface::class, true),
            // The context keys its entries provide their tree, or null for a snippet, whose context field declares them
            'provides' => EntryContext::keysOf($contentType),
            'layouts' => $this->layouts($contentType->code),
            // Its own fields, which the entry settings render: shared ones on its model, then translatable ones on its translations
            'fields' => $this->entryFields($contentType),
        ];
    }

    /**
     * The content type's layouts, the default first and marked. A disabled one is marked too: the picker leaves it out,
     * and an entry in it is flagged as one in a layout the code no longer has, but its fields are known, so values carry
     * over from it when an editor picks another
     *
     * @return list<array<string, mixed>>
     */
    private function layouts(string $contentType): array
    {
        $layouts = $this->layouts->all($contentType);
        $default = [] === $layouts ? null : $this->layouts->getDefault($contentType)->code;

        return array_map(fn (LayoutMetadata $layout): array => [
            'code' => $layout->code,
            'label' => $this->translator->trans($layout->label),
            'version' => $layout->version,
            'thumbnail' => $layout->thumbnail,
            'default' => $layout->code === $default,
            'disabled' => $layout->disabled,
            'shows' => $layout->shows,
            'fields' => $this->fields($layout->fields),
        ], $layouts);
    }

    /**
     * A condition kind, whose parameters the builder renders as fields: parameter values are shared by all translations
     *
     * @return array<string, mixed>
     */
    private function conditionKind(ConditionKindMetadata $conditionKind): array
    {
        return [
            'code' => $conditionKind->code,
            'label' => $this->translator->trans($conditionKind->label),
            'matches' => $this->translator->trans($conditionKind->matches),
            'matchesNot' => $this->translator->trans($conditionKind->matchesNot),
            // Offered only where the entry provides these context keys or the snippet declares them
            'needs' => $conditionKind->needs,
            'fields' => $this->sharedFields($conditionKind->fields),
        ];
    }

    /**
     * The sources editors may pick, whose parameters the builder renders as fields shared by all translations. A
     * disabled one is left out: the lists it fills already keep resolving through it, but editors aren't offered it
     *
     * @return list<array<string, mixed>>
     */
    private function sources(): array
    {
        $sources = array_filter($this->sources->all(), static fn (SourceMetadata $source): bool => !$source->disabled);

        return array_values(array_map(fn (SourceMetadata $source): array => [
            'code' => $source->code,
            'label' => $this->translator->trans($source->label),
            'needs' => $source->needs,
            // The content types whose entries it fits as the entry, such as categories for In this category, or null for any
            'entryContentTypes' => $this->entryContentTypes($source),
            'fields' => $this->sharedFields($source->fields),
        ], $sources));
    }

    /**
     * The codes of the content types editors edit whose entries a source that fits only some fits, or null for one that
     * fits any
     *
     * @return list<string>|null
     */
    private function entryContentTypes(SourceMetadata $source): ?array
    {
        if (!$source->source instanceof EntryContextAwareInterface) {
            return null;
        }

        $codes = array_map(static fn (ContentTypeMetadata $contentType): string => $contentType->code, $this->contentTypes->enabled());

        return array_values(array_filter($codes, $source->fitsEntryContentType(...)));
    }

    /**
     * A content type's own fields, shared on its model and translatable on its translations whatever their field types'
     * defaults, and never bound: an entry is the host-page context, and has none of its own
     *
     * @return list<array{name: string, schema: array<string, mixed>}>
     */
    private function entryFields(ContentTypeMetadata $contentType): array
    {
        return array_values(array_map(
            fn (FieldMetadata $field): array => ['name' => $field->name, 'schema' => array_diff_key($this->schema($field), ['x-cms-bindable' => true, 'x-cms-binds-inside' => true])],
            [...$this->contentTypeFields->shared($contentType), ...$this->contentTypeFields->translatable($contentType)],
        ));
    }

    /**
     * Parameter fields, shared by all translations whatever their field type's default, and never bound
     *
     * @param array<string, FieldMetadata> $fields
     *
     * @return list<array{name: string, schema: array<string, mixed>}>
     */
    private function sharedFields(array $fields): array
    {
        return array_values(array_map(
            fn (FieldMetadata $field): array => ['name' => $field->name, 'schema' => array_diff_key(array_replace($this->schema($field), ['x-cms-translatable' => false]), ['x-cms-bindable' => true])],
            $fields,
        ));
    }

    /**
     * @param list<AllowedBlockType> $allowed
     *
     * @return list<array{type: string, min: int|null, max: int|null}>
     */
    private static function allowed(array $allowed): array
    {
        return array_map(static fn (AllowedBlockType $type): array => ['type' => $type->code, 'min' => $type->min, 'max' => $type->max], $allowed);
    }

    /**
     * The JSON Schema for one value from the field type's handler, plus the x-cms-* keywords the plugin fills.
     * See "Adding a field type" in docs/spec/extending.md
     *
     * @return array<string, mixed>
     */
    private function schema(FieldMetadata $field): array
    {
        $schema = $this->fieldHandlers->get($field->field)->schema($field->field);

        if (null !== $field->default && !array_key_exists('default', $schema)) {
            $schema['default'] = $field->default;
        }

        $schema['x-cms-type'] = $field->type->code;
        if (null !== $field->type->control) {
            $schema['x-cms-control'] = $field->type->control;
        }

        $schema['x-cms-label'] = $this->translator->trans($field->field->label);
        $schema['x-cms-help'] = null === $field->field->help ? null : $this->translator->trans($field->field->help);
        $schema['x-cms-required'] = $field->field->required;
        $schema['x-cms-translatable'] = $field->translatable;
        if ([] !== $field->type->bindable) {
            $schema['x-cms-bindable'] = array_map(static fn (BindingType $type): string => $type->value, $field->type->bindable);
        }

        if ($field->bindsInside()) {
            $schema['x-cms-binds-inside'] = true;
        }

        return $schema;
    }
}
