<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\Loader;

use Exception;
use Setono\SyliusCMSPlugin\Model\TemplateInterface;
use Setono\SyliusCMSPlugin\Repository\TemplateRepositoryInterface;
use Twig\Error\LoaderError;
use Twig\Loader\LoaderInterface;
use Twig\Source;

final class TemplateLoader implements LoaderInterface
{
    /** @var array<string, TemplateInterface|null> */
    private array $cache = [];

    public function __construct(private readonly TemplateRepositoryInterface $templateRepository)
    {
    }

    /**
     * @param string $name
     */
    public function getSourceContext($name): Source
    {
        $template = $this->getTemplate($name);

        return new Source((string) $template->getSource(), $name);
    }

    /**
     * @param string $name
     */
    public function exists($name): bool
    {
        return null !== $this->findTemplate($name);
    }

    /**
     * @param string $name
     */
    public function getCacheKey($name): string
    {
        return $name;
    }

    /**
     * @param string $name
     * @param int $time
     */
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
            } catch (Exception $e) {
                // exceptions can be thrown here when:
                // 1. there's no connection to the database
                // 2. the table has not been created yet
                // 3. some new fields has been created in new versions of the plugin, but not migrated yet
                // 4. other things happen that corresponds to the child classes of \Doctrine\DBAL\Exception
                throw new LoaderError($e->getMessage(), -1, null, $e);
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
