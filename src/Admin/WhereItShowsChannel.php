<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

/**
 * A row of the overview page's Where it shows grid: one of the store's channels, with a cell per locale it has
 */
final readonly class WhereItShowsChannel
{
    /**
     * @param string $name the channel's name, or its code when it has none
     * @param bool $enabled whether the channel is enabled: a disabled one shows shoppers nothing
     * @param array<string, WhereItShowsCell> $cells by locale code, the channel's default locale first, then the others by code
     */
    public function __construct(
        public string $code,
        public string $name,
        public bool $enabled,
        public array $cells,
    ) {
    }
}
