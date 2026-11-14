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
     */
    public function __construct(
        private readonly array $classes,
        private readonly FieldReader $fieldReader,
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

        return $this->blockTypes[$code] ??= $this->read($this->classes[$code]);
    }

    public function all(): array
    {
        return array_map($this->get(...), array_keys($this->classes));
    }

    /**
     * @param class-string $class
     */
    private function read(string $class): BlockTypeMetadata
    {
        $attribute = AttributeReader::blockType($class) ?? throw new \LogicException(sprintf('The class %s must carry #[BlockType]', $class));

        return new BlockTypeMetadata(
            $attribute->code,
            $attribute->label,
            $attribute->version,
            $attribute->template,
            $class,
            $this->fieldReader->read($class),
        );
    }
}
