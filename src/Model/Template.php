<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Component\Resource\Model\TimestampableTrait;

class Template implements TemplateInterface
{
    use TimestampableTrait;

    use InternalDescriptionAwareTrait;

    protected ?int $id = null;

    protected ?string $code = null;

    protected ?string $source = null;

    /** @var list<string> */
    protected array $sections = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(string $source): void
    {
        $this->source = $source;
    }

    public function getSections(): array
    {
        return $this->sections;
    }

    /**
     * @param list<string> $sections
     */
    public function setSections(array $sections): void
    {
        $this->sections = [];
        foreach ($sections as $section) {
            $this->addSection($section);
        }
    }

    public function addSection(string $section): void
    {
        $this->sections[] = $section;
    }

    public function __toString(): string
    {
        return (string) $this->getCode();
    }
}
