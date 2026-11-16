<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

use Psr\Container\ContainerInterface;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;

/**
 * The condition kinds: services carrying #[AsConditionKind], which the container hands this registry by code, with
 * their classes. It reads their attributes, and the fields of their parameters classes, on first use
 */
final class ConditionKindRegistry implements ConditionKindRegistryInterface
{
    /** @var array<string, ConditionKindMetadata> */
    private array $conditionKinds = [];

    /**
     * @param array<string, class-string> $classes condition kind code => class
     * @param ContainerInterface $kinds the condition kind services, by code
     */
    public function __construct(
        private readonly array $classes,
        private readonly FieldReader $fieldReader,
        private readonly ContainerInterface $kinds,
    ) {
    }

    public function has(string $code): bool
    {
        return isset($this->classes[$code]);
    }

    public function get(string $code): ConditionKindMetadata
    {
        if (!isset($this->classes[$code])) {
            throw new UnknownConditionKindException($code);
        }

        return $this->conditionKinds[$code] ??= $this->read($code, $this->classes[$code]);
    }

    public function all(): array
    {
        return array_map($this->get(...), array_keys($this->classes));
    }

    /**
     * @param class-string $class
     */
    private function read(string $code, string $class): ConditionKindMetadata
    {
        $attribute = AttributeReader::conditionKind($class) ?? throw new \LogicException(sprintf('The class %s must carry #[AsConditionKind]', $class));

        $kind = $this->kinds->get($code);
        if (!$kind instanceof ConditionKindInterface) {
            throw new \LogicException(sprintf('The condition kind "%s" must implement %s', $code, ConditionKindInterface::class));
        }

        return new ConditionKindMetadata(
            $attribute->code,
            $attribute->label,
            $attribute->needs,
            $attribute->reads,
            $attribute->params,
            null === $attribute->params ? [] : $this->fieldReader->read($attribute->params),
            $kind,
        );
    }
}
