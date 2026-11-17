<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

use Setono\SyliusCMSPlugin\Attribute\Style;

/**
 * Block types are registered as metadata, not as services, because they're hydrated per use.
 * The container hands this registry the block type classes by code, and it reads their attributes on first use
 */
final class BlockTypeRegistry implements BlockTypeRegistryInterface
{
    /** @var array<string, BlockTypeMetadata> */
    private array $blockTypes = [];

    /**
     * @param array<string, class-string> $classes block type code => class
     * @param list<string> $disabled the codes of the block types switched off in setono_sylius_cms.disabled.block_types
     * @param array<string, array<string, array{label: string, class: string}>> $styles the block styles added in
     *                                                                                setono_sylius_cms.block_styles, by block type code and style code
     */
    public function __construct(
        private readonly array $classes,
        private readonly FieldReader $fieldReader,
        private readonly array $disabled = [],
        private readonly array $styles = [],
    ) {
    }

    public function has(string $code): bool
    {
        return isset($this->classes[$code]);
    }

    public function get(string $code): BlockTypeMetadata
    {
        if (!isset($this->classes[$code])) {
            throw new UnknownBlockTypeException($code);
        }

        return $this->blockTypes[$code] ??= $this->read($this->classes[$code], in_array($code, $this->disabled, true), $this->styles[$code] ?? []);
    }

    public function getByClass(string $class): BlockTypeMetadata
    {
        $code = array_search($class, $this->classes, true);
        if (false === $code) {
            throw UnknownBlockTypeException::forClass($class);
        }

        return $this->get($code);
    }

    public function all(): array
    {
        return array_map($this->get(...), array_keys($this->classes));
    }

    /**
     * @param class-string $class
     * @param array<string, array{label: string, class: string}> $configuredStyles
     */
    private function read(string $class, bool $disabled, array $configuredStyles): BlockTypeMetadata
    {
        $attribute = AttributeReader::blockType($class) ?? throw new \LogicException(sprintf('The class %s must carry #[BlockType]', $class));

        return new BlockTypeMetadata(
            $attribute->code,
            $attribute->label,
            $attribute->version,
            $attribute->template,
            $class,
            $this->fieldReader->read($class),
            $attribute->children,
            $attribute->parents,
            $attribute->thumbnail,
            $disabled,
            self::styles($attribute->styles, $configuredStyles),
            $attribute->needs,
        );
    }

    /**
     * The styles a block type declares, then those added in config. One added with the code of a declared one replaces it in its place
     *
     * @param list<Style> $declared
     * @param array<string, array{label: string, class: string}> $configured
     *
     * @return array<string, Style>
     */
    private static function styles(array $declared, array $configured): array
    {
        $styles = [];
        foreach ($declared as $style) {
            $styles[$style->code] = $style;
        }

        foreach ($configured as $code => $style) {
            $styles[$code] = new Style($code, $style['label'], $style['class']);
        }

        return $styles;
    }
}
