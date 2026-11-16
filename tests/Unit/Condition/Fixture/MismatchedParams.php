<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture;

use Setono\SyliusCMSPlugin\Field\Text;

/**
 * A parameters class whose property can't hold what its field type hydrates
 */
final class MismatchedParams
{
    #[Text(label: 'Count')]
    public ?int $count = 3;

    #[Text(label: 'Name')]
    public ?string $name = null;
}
