<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Element;

use Setono\SyliusCMSPlugin\Model\ElementInterface;

/**
 * @template T of ElementInterface
 */
abstract class AbstractElement
{
    /** @psalm-var T */
    protected ElementInterface $resource;

    protected string $rendered;

    /**
     * @psalm-param T $resource
     */
    public function __construct(ElementInterface $resource, string $rendered)
    {
        $this->resource = $resource;
        $this->rendered = $rendered;
    }

    /**
     * @psalm-return T
     */
    public function getResource(): ElementInterface
    {
        return $this->resource;
    }

    public function getRendered(): string
    {
        return $this->rendered;
    }
}
