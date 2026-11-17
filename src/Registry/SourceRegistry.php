<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

use Psr\Container\ContainerInterface;
use Setono\SyliusCMSPlugin\Source\SourceInterface;

/**
 * The sources: services carrying #[AsSource], which the container hands this registry by code, with their classes. It
 * reads their attributes, and the fields of their parameters classes, on first use
 */
final class SourceRegistry implements SourceRegistryInterface
{
    /** @var array<string, SourceMetadata> */
    private array $sources = [];

    /**
     * @param array<string, class-string> $classes source code => class
     * @param ContainerInterface $services the source services, by code
     * @param list<string> $disabled the codes switched off in setono_sylius_cms.disabled.sources
     */
    public function __construct(
        private readonly array $classes,
        private readonly FieldReader $fieldReader,
        private readonly ContainerInterface $services,
        private readonly array $disabled = [],
    ) {
    }

    public function has(string $code): bool
    {
        return isset($this->classes[$code]);
    }

    public function get(string $code): SourceMetadata
    {
        if (!isset($this->classes[$code])) {
            throw new UnknownSourceException($code);
        }

        return $this->sources[$code] ??= $this->read($code, $this->classes[$code]);
    }

    public function all(): array
    {
        return array_map($this->get(...), array_keys($this->classes));
    }

    /**
     * @param class-string $class
     */
    private function read(string $code, string $class): SourceMetadata
    {
        $attribute = AttributeReader::source($class) ?? throw new \LogicException(sprintf('The class %s must carry #[AsSource]', $class));

        $source = $this->services->get($code);
        if (!$source instanceof SourceInterface) {
            throw new \LogicException(sprintf('The source "%s" must implement %s', $code, SourceInterface::class));
        }

        return new SourceMetadata(
            $attribute->code,
            $attribute->label,
            $attribute->of,
            $attribute->needs,
            $attribute->params,
            null === $attribute->params ? [] : $this->fieldReader->read($attribute->params),
            $source,
            in_array($code, $this->disabled, true),
        );
    }
}
