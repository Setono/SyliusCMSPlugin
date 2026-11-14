<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

/**
 * Layouts are registered as metadata, not as services, because they're hydrated per use. The container hands this
 * registry the layout classes by content type and code, and it reads their attributes on first use
 */
final class LayoutRegistry implements LayoutRegistryInterface
{
    /** @var array<string, array<string, LayoutMetadata>> */
    private array $layouts = [];

    /**
     * @param array<string, array<string, class-string>> $classes content type code => layout code => class
     */
    public function __construct(
        private readonly array $classes,
        private readonly FieldReader $fieldReader,
    ) {
    }

    public function has(string $contentType, string $code): bool
    {
        return isset($this->classes[$contentType][$code]);
    }

    public function get(string $contentType, string $code): LayoutMetadata
    {
        if (!isset($this->classes[$contentType][$code])) {
            throw new UnknownLayoutException($contentType, $code);
        }

        return $this->layouts[$contentType][$code] ??= $this->read($contentType, $this->classes[$contentType][$code]);
    }

    /**
     * @param class-string $class
     */
    private function read(string $contentType, string $class): LayoutMetadata
    {
        $attribute = AttributeReader::layout($class) ?? throw new \LogicException(sprintf('The class %s must carry #[Layout]', $class));

        return new LayoutMetadata($attribute->code, $contentType, $attribute->version, $class, $this->fieldReader->read($class));
    }
}
