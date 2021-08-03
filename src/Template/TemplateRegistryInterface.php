<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Template;

interface TemplateRegistryInterface
{
    public function all(): array;

    public function add(Template $template): void;

    public function has(string $key): bool;

    public function get(string $key): object;
}
