<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field\Fixture;

use Setono\SyliusCMSPlugin\Field\Snippet;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;

final class WithSnippet
{
    #[Snippet(label: 'app.footer')]
    public ?SnippetInterface $footer = null;
}
