<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\Loader;

use Setono\SyliusCMSPlugin\Twig\Loader\ElementLoader\ElementLoaderInterface;
use Setono\SyliusCMSPlugin\Twig\Loader\ElementLoader\LogicalTemplateName;
use Twig\Error\LoaderError;
use Twig\Loader\LoaderInterface;
use Twig\Source;

final class ElementLoader implements LoaderInterface
{
    /** @var list<ElementLoaderInterface> */
    private array $elementLoaders = [];

    public function add(ElementLoaderInterface $elementLoader): void
    {
        $this->elementLoaders[] = $elementLoader;
    }

    public function getSourceContext($name): Source
    {
        return $this->delegate($name, static function (LogicalTemplateName $logicalTemplateName, ElementLoaderInterface $elementLoader): Source {
            return new Source($elementLoader->getSource($logicalTemplateName), (string) $logicalTemplateName);
        });
    }

    public function exists($name): bool
    {
        return $this->delegate($name, static function (LogicalTemplateName $logicalTemplateName, ElementLoaderInterface $elementLoader): bool {
            return $elementLoader->exists($logicalTemplateName);
        });
    }

    // todo should throw LoaderError if $name does not exist - at least according to the interface docs
    public function getCacheKey($name): string
    {
        return (string) $this->getLogicalTemplateName($name);
    }

    public function isFresh($name, $time): bool
    {
        return $this->delegate($name, static function (LogicalTemplateName $logicalTemplateName, ElementLoaderInterface $elementLoader) use ($time): bool {
            return $elementLoader->isFresh($logicalTemplateName, $time);
        });
    }

    /**
     * @param callable(LogicalTemplateName, ElementLoaderInterface): mixed $callable
     * @return mixed
     */
    private function delegate(string $name, callable $callable)
    {
        $logicalTemplateName = $this->getLogicalTemplateName($name);

        foreach ($this->elementLoaders as $elementLoader) {
            if ($elementLoader->supports($logicalTemplateName)) {
                return $callable($logicalTemplateName, $elementLoader);
            }
        }

        throw new LoaderError(sprintf('No element loader supports the logical template name "%s"', (string) $logicalTemplateName));
    }

    private function getLogicalTemplateName(string $name): LogicalTemplateName
    {
        try {
            return LogicalTemplateName::createFromString($name);
        } catch (\InvalidArgumentException $e) {
            throw new LoaderError($e->getMessage(), -1, null, $e);
        }
    }
}
