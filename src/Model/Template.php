<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Component\Resource\Model\TimestampableTrait;

class Template implements TemplateInterface
{
    use CodeAwareTrait;

    use IdAwareTrait;

    use InternalDescriptionAwareTrait;

    use TimestampableTrait;

    protected ?string $code = null;

    protected ?string $source = null;

    /** @var list<string> */
    protected array $sections = [];

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(string $source): void
    {
        $this->source = $source;
    }

    public function __toString(): string
    {
        return (string) $this->getCode();
    }
}
