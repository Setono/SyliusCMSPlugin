<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Stack;

use Setono\SyliusCMSPlugin\Model\ElementInterface;

final class ElementId
{
    /** @readonly  */
    public int $id;

    /** @readonly  */
    public string $code;

    /** @readonly  */
    public string $identifier;

    /** @readonly  */
    public string $type;

    public function __construct(int $id, string $code, string $identifier, string $type)
    {
        $this->id = $id;
        $this->code = $code;
        $this->identifier = $identifier;
        $this->type = $type;
    }

    public function isAsset(): bool
    {
        return ElementInterface::TYPE_ASSET === $this->type;
    }

    public function isBlock(): bool
    {
        return ElementInterface::TYPE_BLOCK === $this->type;
    }

    public function isCarousel(): bool
    {
        return ElementInterface::TYPE_CAROUSEL === $this->type;
    }

    public function isView(): bool
    {
        return ElementInterface::TYPE_VIEW === $this->type;
    }
}
