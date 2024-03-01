<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Template;

use Setono\SyliusCMSPlugin\Repository\TemplateRepositoryInterface;
use Setono\SyliusCMSPlugin\Twig\Extractor\SectionExtractorInterface;

final class RegistryFactory implements RegistryFactoryInterface
{
    /**
     * @param list<array{code: string, label: string, description: string}> $templates
     */
    public function __construct(
        private readonly SectionExtractorInterface $sectionExtractor,
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly array $templates = [],
    ) {
    }

    public function create(): RegistryInterface
    {
        $registry = new Registry();

        foreach ($this->templates as $template) {
            $registry->add(Template::createFromArray($template, $this->sectionExtractor->extract($template['code'])));
        }

        foreach ($this->templateRepository->findAll() as $template) {
            $registry->add(Template::createFromEntity(
                $template,
                $this->sectionExtractor->extract((string) $template->getCode()),
            ));
        }

        return $registry;
    }
}
