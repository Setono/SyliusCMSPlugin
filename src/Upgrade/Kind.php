<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Upgrade;

/**
 * What an upgrader upgrades: a block type, a layout or a content type
 */
enum Kind: string
{
    case BlockType = 'block_type';
    case Layout = 'layout';
    case ContentType = 'content_type';

    /**
     * The id of the upgrader from version $from of the block type, layout or content type with the code $code, in the
     * upgraders' service locator. A layout's code is unique only within its content type, so it's given as
     * "<content type code>:<layout code>"
     */
    public function upgraderId(string $code, int $from): string
    {
        return sprintf('%s:%s:%d', $this->value, $code, $from);
    }
}
