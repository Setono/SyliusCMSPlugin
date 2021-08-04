<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Template;

final class Template
{
    private string $key;

    private string $path;

    private string $label;

    public function __construct(string $key, string $path, string $label)
    {
        $this->key = $key;
        $this->path = $path;
        $this->label = $label;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getLabel(): string
    {
        return $this->label;
    }
}
