<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\Loader;

use Setono\SyliusCMSPlugin\Model\TemplateInterface;
use Setono\SyliusCMSPlugin\Repository\TemplateRepositoryInterface;
use Twig\Error\LoaderError;
use Twig\Loader\LoaderInterface;
use Twig\Source;

final class DoctrineLoader implements LoaderInterface
{
    private TemplateRepositoryInterface $templateRepository;

    public function __construct(TemplateRepositoryInterface $templateRepository)
    {
        $this->templateRepository = $templateRepository;
    }

    public function getSourceContext($name): Source
    {
        $template = $this->getTemplate($name);

        return new Source((string) $template->getSource(), $name);
    }

    public function exists($name): bool
    {
        return $this->templateRepository->exists($name);
    }

    public function getCacheKey($name): string
    {
        return $name;
    }

    public function isFresh($name, $time): bool
    {
        $template = $this->getTemplate($name);
        $updatedAt = $template->getUpdatedAt();
        if (null === $updatedAt) {
            return false;
        }

        return $updatedAt->getTimestamp() <= $time;
    }

    private function getTemplate(string $code): TemplateInterface
    {
        $template = $this->templateRepository->findOneByCode($code);
        if (null === $template) {
            throw new LoaderError(sprintf('Template "%s" does not exist.', $code));
        }

        return $template;
    }
}
