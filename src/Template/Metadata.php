<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Template;

final class Metadata
{
    /** @var array<array-key, string> */
    private array $sections;

    /**
     * @param array<array-key, string> $sections
     */
    public function __construct(array $sections = [])
    {
        $this->sections = $sections;
    }

    /**
     * @return array<array-key, string>
     */
    public function getSections(): array
    {
        return $this->sections;
    }
}
