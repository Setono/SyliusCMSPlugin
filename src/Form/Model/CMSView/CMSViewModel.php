<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Model\CMSView;

use Sylius\Component\Resource\Model\CodeAwareInterface;

/** @internal */
class CMSViewModel implements CodeAwareInterface
{
    public ?string $code = null;

    public string $template = '';

    /** @var array<array-key, CMSSectionModel> */
    public array $sections = [];

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code = null): void
    {
        $this->code = $code;
    }
}
