<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\Loader\ElementLoader;

/**
 * This interface is highly inspired by the \Twig\Loader\LoaderInterface.
 *
 * Implement this interface for loaders that will load CMS elements
 */
interface ElementLoaderInterface
{
    /**
     * Returns the Twig source for the given template name
     */
    public function getSource(LogicalTemplateName $logicalTemplateName): string;

    /**
     * Returns true if the template is still fresh
     */
    public function isFresh(LogicalTemplateName $logicalTemplateName, int $time): bool;

    /**
     * Check if we have the source code of a template, given its name.
     */
    public function exists(LogicalTemplateName $logicalTemplateName): bool;

    /**
     * Returns true if this element loader supports the template (most likely the type)
     */
    public function supports(LogicalTemplateName $logicalTemplateName): bool;
}
