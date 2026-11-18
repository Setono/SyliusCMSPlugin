<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * Stored blocks of the shipped block types, as tests put them in trees
 */
final class Blocks
{
    private function __construct()
    {
    }

    /**
     * A Snippet block with the key that shows the snippet
     */
    public static function snippet(string $key, SnippetInterface $snippet): StoredBlock
    {
        return new StoredBlock($key, 'snippet', 1, fields: ['snippet' => $snippet->getUuid()]);
    }
}
