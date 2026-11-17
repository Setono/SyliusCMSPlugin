<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

use Psr\Container\ContainerInterface;
use Setono\SyliusCMSPlugin\Link\LinkTargetInterface;

/**
 * The link targets: services carrying #[AsLinkTarget], which the container hands this registry by code, with their
 * classes. It reads their attributes on first use
 */
final class LinkTargetRegistry implements LinkTargetRegistryInterface
{
    /** @var array<string, LinkTargetMetadata> */
    private array $linkTargets = [];

    /**
     * @param array<string, class-string> $classes link target code => class
     * @param ContainerInterface $targets the link target services, by code
     */
    public function __construct(
        private readonly array $classes,
        private readonly ContainerInterface $targets,
    ) {
    }

    public function has(string $code): bool
    {
        return isset($this->classes[$code]);
    }

    public function get(string $code): LinkTargetMetadata
    {
        if (!isset($this->classes[$code])) {
            throw new UnknownLinkTargetException($code);
        }

        return $this->linkTargets[$code] ??= $this->read($code, $this->classes[$code]);
    }

    public function all(): array
    {
        return array_map($this->get(...), array_keys($this->classes));
    }

    /**
     * @param class-string $class
     */
    private function read(string $code, string $class): LinkTargetMetadata
    {
        $attribute = AttributeReader::linkTarget($class) ?? throw new \LogicException(sprintf('The class %s must carry #[AsLinkTarget]', $class));

        $target = $this->targets->get($code);
        if (!$target instanceof LinkTargetInterface) {
            throw new \LogicException(sprintf('The link target "%s" must implement %s', $code, LinkTargetInterface::class));
        }

        return new LinkTargetMetadata($attribute->code, $attribute->label, $attribute->translatableRef, $target);
    }
}
