<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Template;

interface RegistryInterface
{
    /**
     * This will return all available templates indexed by the code of the Template
     *
     * @return array<string, Template>
     */
    public function all(): array;

    /**
     * Adds a template to the registry
     *
     * @throws \RuntimeException if the template with the given code already exists
     */
    public function add(Template $template): void;

    /**
     * Returns true if a template with the given code exists
     */
    public function has(string $code): bool;

    /**
     * Returns the template with the given code
     *
     * @throws \RuntimeException if the template with the given code doesn't exist
     */
    public function get(string $code): Template;
}
