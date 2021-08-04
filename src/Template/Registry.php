<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Template;

final class Registry implements RegistryInterface
{
    /** @var array<string, Template> */
    private array $templates = [];

    public function all(): array
    {
        return $this->templates;
    }

    public function add(Template $template): void
    {
        if ($this->has($template->getKey())) {
            throw new \RuntimeException(sprintf('A template with key "%s" already exists', $template->getKey()));
        }

        $this->templates[$template->getKey()] = $template;
    }

    public function has(string $key): bool
    {
        return isset($this->templates[$key]);
    }

    public function get(string $key): Template
    {
        if (!$this->has($key)) {
            throw new \RuntimeException(sprintf('A template with key "%s" does not exist', $key));
        }

        return $this->templates[$key];
    }
}
