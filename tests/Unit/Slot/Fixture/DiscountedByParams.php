<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Slot\Fixture;

use Setono\SyliusCMSPlugin\Field\Number;
use Setono\SyliusCMSPlugin\Validation\Constraint\ExactlyOneOf;

#[ExactlyOneOf(fields: ['amount', 'percentage'])]
final class DiscountedByParams
{
    #[Number(label: 'app.discounted_by.amount')]
    public ?int $amount = null;

    #[Number(label: 'app.discounted_by.percentage')]
    public ?int $percentage = null;
}
