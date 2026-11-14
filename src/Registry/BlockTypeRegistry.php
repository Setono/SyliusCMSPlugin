<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

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
     */
    public function __construct(
        private readonly array $classes,
        private readonly FieldReader $fieldReader,
        private readonly array $disabled = [],
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

        return $this->blockTypes[$code] ??= $this->read($this->classes[$code], in_array($code, $this->disabled, true));
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
     */
    private function read(string $class, bool $disabled): BlockTypeMetadata
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
        );
    }
}
