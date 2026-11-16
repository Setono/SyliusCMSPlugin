<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder;

use Setono\SyliusCMSPlugin\Attribute\Style;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Registry\AllowedBlockType;
use Setono\SyliusCMSPlugin\Registry\BlockTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ConditionKindMetadata;
use Setono\SyliusCMSPlugin\Registry\ConditionKindRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;
use Setono\SyliusCMSPlugin\Registry\LayoutMetadata;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\NestingRulesInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What the builder's registry endpoint answers: the block types with their field schemas, nesting rules and block styles,
 * what the top level of each content type may hold, the layouts editors can pick for it with their field schemas, the
 * condition kinds with their parameters' field schemas, and the UI strings, translated in the current locale,
 * which in the admin is the admin user's locale. The shape is internal to the builder
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
        private TranslatorBagInterface $catalogues,
        private ConditionKindRegistryInterface $conditionKinds,
        private LayoutRegistryInterface $layouts,
    ) {
    }

    /**
     * @return array{blockTypes: list<array<string, mixed>>, contentTypes: list<array<string, mixed>>, conditionKinds: list<array<string, mixed>>, strings: array<string, string>}
     */
    public function export(): array
    {
        return [
            'blockTypes' => array_map($this->blockType(...), $this->blockTypes->all()),
            'contentTypes' => array_map($this->contentType(...), $this->contentTypes->all()),
            'conditionKinds' => array_map($this->conditionKind(...), $this->conditionKinds->all()),
            'strings' => $this->strings(),
        ];
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
            // Whether its entries can be a channel's homepage, which only pages can, so the settings offer "Homepage of"
            'homepage' => is_a($contentType->modelClass, PageInterface::class, true),
            'layouts' => $this->layouts($contentType->code),
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
            'fields' => array_values(array_map(
                fn (FieldMetadata $field): array => ['name' => $field->name, 'schema' => array_replace($this->schema($field), ['x-cms-translatable' => false])],
                $conditionKind->fields,
            )),
        ];
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

        return $schema;
    }

    /**
     * @return array<string, string>
     */
    private function strings(): array
    {
        $strings = [];
        for ($catalogue = $this->catalogues->getCatalogue(); null !== $catalogue; $catalogue = $catalogue->getFallbackCatalogue()) {
            foreach (array_keys($catalogue->all('messages')) as $id) {
                if (is_string($id) && str_starts_with($id, self::STRINGS_PREFIX)) {
                    $strings[$id] ??= $this->translator->trans($id);
                }
            }
        }

        ksort($strings);

        return $strings;
    }
}
