<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Text;

/**
 * A block type with a field of a project's own field type
 */
#[BlockType(code: 'review', label: 'app.review', template: 'review.html.twig')]
final class Review
{
    #[Text(label: 'app.quote')]
    public ?string $quote = null;

    #[Rating(label: 'app.rating')]
    public ?int $rating = null;
}
