<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\Loader;

use InvalidArgumentException;
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

    /**
     * This method is always called before any of the other methods in the LoaderInterface.
     * This implies that we can expect the $name to be valid in all other methods in this class
     *
     * NOTICE
     * The interface states that this method should throw a LoaderError if the $name does not exist.
     * We only do this in subsequent methods (i.e. getSourceContext and isFresh). This is because Twig
     * will call this method everytime Twig needs a cache key and to load a template (also existing compiled ones)
     * Twig needs the cache key, hence exists is called EVERY time a template is loaded and with our approach this
     * would mean we needed to hit the database for EVERY cms element referenced throughout the application
     */
    public function exists($name): bool
    {
        try {
            LogicalTemplateName::createFromString($name);
        } catch (InvalidArgumentException $e) {
            return false;
        }

        return true;
    }

    public function getSourceContext($name): Source
    {
        $logicalTemplateName = LogicalTemplateName::createFromString($name);

        return new Source(
            $this->getElementLoader($logicalTemplateName)->getSource($logicalTemplateName),
            (string) $logicalTemplateName
        );
    }

    // todo should throw LoaderError if $name does not exist - at least according to the interface docs
    public function getCacheKey($name): string
    {
        return (string) LogicalTemplateName::createFromString($name);
    }

    public function isFresh($name, $time): bool
    {
        $logicalTemplateName = LogicalTemplateName::createFromString($name);

        return $this->getElementLoader($logicalTemplateName)
            ->isFresh($logicalTemplateName, $time);
    }

    private function getElementLoader(LogicalTemplateName $logicalTemplateName): ElementLoaderInterface
    {
        foreach ($this->elementLoaders as $elementLoader) {
            if ($elementLoader->supports($logicalTemplateName)) {
                return $elementLoader;
            }
        }

        throw new LoaderError(sprintf(
            'No element loader supports the logical template name "%s"',
            (string) $logicalTemplateName
        ));
    }
}
