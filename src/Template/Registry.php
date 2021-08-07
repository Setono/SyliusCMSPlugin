<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Template;

/**
 * Not marked final because we will lazy load this class
 *
 * @final
 */
class Registry implements RegistryInterface
{
    /** @var array<string, Template> */
    private array $templates = [];

    public function all(): array
    {
        return $this->templates;
    }

    public function add(Template $template): void
    {
        if ($this->has($template->getCode())) {
            throw new \RuntimeException(sprintf('A template with key "%s" already exists', $template->getCode()));
        }

        $this->templates[$template->getCode()] = $template;
    }

    public function has(string $code): bool
    {
        return isset($this->templates[$code]);
    }

    public function get(string $code): Template
    {
        if (!$this->has($code)) {
            throw new \RuntimeException(sprintf('A template with key "%s" does not exist', $code));
        }

        return $this->templates[$code];
    }
}
