<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Twig\Fixture;

/**
 * Something that isn't a block, whose properties cms_field() prints, or refuses to
 */
final class NotABlock
{
    public string $text = 'Hello';

    public int $number = 42;

    public \Stringable $stringable;

    /** @var list<string> */
    public array $list = ['a'];

    private string $secret = 'hidden'; // @phpstan-ignore property.onlyWritten

    public function __construct()
    {
        $this->stringable = new class() implements \Stringable {
            public function __toString(): string
            {
                return 'A & B';
            }
        };
    }
}
