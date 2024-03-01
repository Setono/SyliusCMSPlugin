<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Template;

use Setono\SyliusCMSPlugin\Model\TemplateInterface;
use Webmozart\Assert\Assert;

final class Template
{
    /**
     * @param list<string> $sections
     */
    public function __construct(
        private readonly string $code,
        private readonly ?string $label = null,
        private readonly ?string $description = null,
        private readonly array $sections = [],
    ) {
    }

    /**
     * @param list<string> $sections
     */
    public static function createFromEntity(TemplateInterface $template, array $sections = []): self
    {
        return new self(
            (string) $template->getCode(),
            null,
            $template->getInternalDescription(),
            $sections,
        );
    }

    /**
     * @param array<string, string|null> $template
     * @param list<string> $sections
     */
    public static function createFromArray(array $template, array $sections = []): self
    {
        Assert::keyExists($template, 'code');
        Assert::string($template['code']);

        $template['label'] ??= null;
        $template['description'] ??= null;

        Assert::nullOrString($template['label']);
        Assert::nullOrString($template['description']);

        return new self($template['code'], $template['label'], $template['description'], $sections);
    }

    /**
     * The code is what Twig refers to as the name of the template, i.e. "@SetonoSyliusCMSPlugin/header.html.twig"
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * If a label is defined this is shown to the user instead of the code.
     * If the label is null, this method will return the code
     */
    public function getLabel(): string
    {
        if (null === $this->label) {
            return $this->getCode();
        }

        return $this->label;
    }

    /**
     * The description (if set) can be used to show a
     * detailed description of the template to the end user
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return list<string>
     */
    public function getSections(): array
    {
        return $this->sections;
    }
}
