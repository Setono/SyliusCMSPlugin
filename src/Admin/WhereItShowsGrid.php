<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

/**
 * The overview page's Where it shows grid: a row per channel of the store, and a column per locale of the channels
 */
final readonly class WhereItShowsGrid
{
    /**
     * @param list<string> $localeCodes the columns: every locale of the channels, in the order the channels first have them
     * @param list<WhereItShowsChannel> $channels the rows, in the order the store lists its channels
     */
    public function __construct(
        public array $localeCodes,
        public array $channels,
    ) {
    }
}
