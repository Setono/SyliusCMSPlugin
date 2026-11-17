<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

use Psr\Container\ContainerInterface;
use Setono\SyliusCMSPlugin\Binding\BindablePropertyInterface;
use Setono\SyliusCMSPlugin\Binding\BindingType;

/**
 * The bindable properties: services carrying #[AsBindableProperty], and the property paths setono_sylius_cms.
 * bindable_properties declares, which the container hands this registry by binding, with their declarations. It reads
 * the fields of their parameters classes on first use. See "Bindable properties" in docs/spec/extending.md
 */
final class BindablePropertyRegistry implements BindablePropertyRegistryInterface
{
    /** @var array<string, BindablePropertyMetadata> */
    private array $properties = [];

    /**
     * @param array<string, array{context_key: string, code: string, label: string, type: string, params: class-string|null}> $declarations
     *                                                                                                                                       binding => declaration, in the order the picker lists them
     * @param ContainerInterface $services the bindable property services, by binding
     */
    public function __construct(
        private readonly array $declarations,
        private readonly FieldReader $fieldReader,
        private readonly ContainerInterface $services,
    ) {
    }

    public function has(string $binding): bool
    {
        return isset($this->declarations[$binding]);
    }

    public function get(string $binding): BindablePropertyMetadata
    {
        if (!isset($this->declarations[$binding])) {
            throw new UnknownBindablePropertyException($binding);
        }

        return $this->properties[$binding] ??= $this->read($binding, $this->declarations[$binding]);
    }

    public function all(): array
    {
        return array_map($this->get(...), array_keys($this->declarations));
    }

    /**
     * @param array{context_key: string, code: string, label: string, type: string, params: class-string|null} $declaration
     */
    private function read(string $binding, array $declaration): BindablePropertyMetadata
    {
        $property = $this->services->get($binding);
        if (!$property instanceof BindablePropertyInterface) {
            throw new \LogicException(sprintf('The bindable property "%s" must implement %s', $binding, BindablePropertyInterface::class));
        }

        return new BindablePropertyMetadata(
            $declaration['context_key'],
            $declaration['code'],
            $declaration['label'],
            BindingType::from($declaration['type']),
            $declaration['params'],
            null === $declaration['params'] ? [] : $this->fieldReader->read($declaration['params']),
            $property,
        );
    }
}
