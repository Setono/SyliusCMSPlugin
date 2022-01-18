<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Renderer;

use Webmozart\Assert\Assert;

final class Response
{
    private string $content;

    /** @var array<array-key, ElementId> */
    private array $elementIds = [];

    /**
     * @param ElementId|array<array-key, ElementId> $elementIds
     */
    public function __construct(string $content, $elementIds = [])
    {
        if (!is_array($elementIds)) {
            $elementIds = [$elementIds];
        }
        Assert::allIsInstanceOf($elementIds, ElementId::class);

        $this->content = $content;

        foreach ($elementIds as $elementId) {
            $this->addElementId($elementId);
        }
    }

    public function addElementId(ElementId $element): void
    {
        if ($this->hasElement($element)) {
            return;
        }

        $this->elementIds[$element->getIdentifier()] = $element;
    }

    private function hasElement(ElementId $element): bool
    {
        return isset($this->elementIds[$element->getIdentifier()]);
    }

    public static function empty(): self
    {
        return new self('');
    }

    public function __toString(): string
    {
        return $this->getContent();
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Returns the elements rendered
     *
     * @return array<array-key, ElementId>
     */
    public function getElementIds(): array
    {
        return $this->elementIds;
    }
}
