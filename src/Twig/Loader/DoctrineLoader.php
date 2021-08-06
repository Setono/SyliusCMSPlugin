<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\Loader;

use Setono\SyliusCMSPlugin\Model\TemplateInterface;
use Setono\SyliusCMSPlugin\Repository\TemplateRepositoryInterface;
use Twig\Error\LoaderError;
use Twig\Loader\LoaderInterface;
use Twig\Source;
use Webmozart\Assert\Assert;

final class DoctrineLoader implements LoaderInterface
{
    private TemplateRepositoryInterface $templateRepository;

    public function __construct(TemplateRepositoryInterface $templateRepository)
    {
        $this->templateRepository = $templateRepository;
    }

    public function getSourceContext($name): Source
    {
        Assert::string($name);

        $template = $this->getTemplate($name);

        return new Source($template->getSource(), $name);
    }

    public function exists($name): bool
    {
        Assert::string($name);

        return $this->templateRepository->exists($name);
    }

    public function getCacheKey($name): string
    {
        Assert::string($name);

        return $name;
    }

    public function isFresh($name, $time): bool
    {
        Assert::string($name);

        $template = $this->getTemplate($name);
        $updatedAt = $template->getUpdatedAt();
        if (null === $updatedAt) {
            return false;
        }

        return $updatedAt <= $time;
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
