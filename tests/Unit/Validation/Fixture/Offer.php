<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Validation\Fixture;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Validation\Constraint\ExactlyOneOf;

/**
 * A project's block type with a constraint that reads two of its fields together: a shared one and a translatable one
 */
#[BlockType(code: 'offer', label: 'app.offer', template: 'offer.html.twig')]
#[ExactlyOneOf(fields: ['code', 'text'])]
final class Offer
{
    #[Text(label: 'Coupon code', translatable: false)]
    public ?string $code = null;

    #[Text(label: 'Offer text')]
    public ?string $text = null;
}
