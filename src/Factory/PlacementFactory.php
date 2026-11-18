<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Factory;

use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Sylius\Resource\Exception\UnsupportedMethodException;

final readonly class PlacementFactory implements PlacementFactoryInterface
{
    /**
     * @param class-string<PlacementInterface> $className the placement resource's model
     */
    public function __construct(private string $className)
    {
    }

    /**
     * A placement always shows a snippet in a slot
     *
     * @throws UnsupportedMethodException
     */
    public function createNew(): PlacementInterface
    {
        throw new UnsupportedMethodException('createNew');
    }

    public function createFor(SnippetInterface $snippet, string $slot, int $position): PlacementInterface
    {
        return new $this->className($snippet, $slot, $position);
    }
}
