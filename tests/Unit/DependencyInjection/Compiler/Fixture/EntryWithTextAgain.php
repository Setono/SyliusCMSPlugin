<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture;

/**
 * A content type's entry class, whose fields can be private
 */
final class EntryWithTextAgain
{
    #[TextAgain(label: 'app.text')]
    private ?string $text = null; // @phpstan-ignore property.onlyWritten, property.unusedType
}
