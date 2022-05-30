<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\Loader;

use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Setono\SyliusCMSPlugin\Model\TemplateInterface;
use Setono\SyliusCMSPlugin\Repository\TemplateRepositoryInterface;
use Twig\Error\LoaderError;
use Twig\Loader\LoaderInterface;
use Twig\Source;

final class TemplateLoader implements LoaderInterface
{
    private TemplateRepositoryInterface $templateRepository;

    /** @var array<string, TemplateInterface|null> */
    private array $cache = [];

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
        return null !== $this->findTemplate($name);
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

    private function findTemplate(string $code): ?TemplateInterface
    {
        if (!array_key_exists($code, $this->cache)) {
            try {
                $template = $this->templateRepository->findOneByCode($code);
            } catch (ConnectionException | TableNotFoundException $e) {
                // these exceptions are thrown either when there's no connection to the database
                // or when the template table hasn't been created yet
                return null;
            }
            $this->cache[$code] = $template;
        }

        return $this->cache[$code];
    }

    private function getTemplate(string $code): TemplateInterface
    {
        $template = $this->findTemplate($code);
        if (null === $template) {
            throw new LoaderError(sprintf('Template "%s" does not exist.', $code));
        }

        return $template;
    }
}
