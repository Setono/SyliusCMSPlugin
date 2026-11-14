<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder;

use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Registry\BlockTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What the builder's registry endpoint answers: the block types with their field schemas, and the UI strings,
 * translated in the current locale, which in the admin is the admin user's locale. The shape is internal to the builder
 */
final readonly class RegistryExporter
{
    private const STRINGS_PREFIX = 'setono_sylius_cms.builder.';

    public function __construct(
        private BlockTypeRegistryInterface $blockTypes,
        private FieldHandlerLocator $fieldHandlers,
        private TranslatorInterface $translator,
        private TranslatorBagInterface $catalogues,
    ) {
    }

    /**
     * @return array{blockTypes: list<array<string, mixed>>, strings: array<string, string>}
     */
    public function export(): array
    {
        return [
            'blockTypes' => array_map($this->blockType(...), $this->blockTypes->all()),
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
            'fields' => array_values(array_map(fn (FieldMetadata $field): array => ['name' => $field->name, 'schema' => $this->schema($field)], $blockType->fields)),
        ];
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
        if (null !== $field->type->widget) {
            $schema['x-cms-widget'] = $field->type->widget;
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
