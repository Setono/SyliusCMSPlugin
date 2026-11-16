<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Builder\Fixture;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Text;

#[BlockType(code: 'store', label: 'app.store', template: 'store.html.twig')]
final class Store
{
    #[Text(label: 'app.name')]
    public ?string $name = null;

    /** @var array<string, mixed>|null */
    #[OpeningHours(label: 'app.opening_hours')]
    public ?array $openingHours = null;
}
