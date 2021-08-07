<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Template;

use Setono\SyliusCMSPlugin\Repository\TemplateRepositoryInterface;

final class RegistryFactory implements RegistryFactoryInterface
{
    private TemplateRepositoryInterface $templateRepository;

    /** @var array<array-key, array<string, string|null>> */
    private array $templates;

    /**
     * @param array<array-key, array<string, string|null>> $templates
     */
    public function __construct(TemplateRepositoryInterface $templateRepository, array $templates = [])
    {
        $this->templateRepository = $templateRepository;
        $this->templates = $templates;
    }

    public function create(): RegistryInterface
    {
        $registry = new Registry();

        foreach ($this->templates as $template) {
            $registry->add(Template::createFromArray($template));
        }

        foreach ($this->templateRepository->findAll() as $template) {
            $registry->add(Template::createFromEntity($template));
        }

        return $registry;
    }
}
