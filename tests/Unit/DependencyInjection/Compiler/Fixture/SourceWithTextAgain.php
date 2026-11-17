<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsSource;
use Sylius\Component\Core\Model\ProductInterface;

#[AsSource(code: 'text_again', label: 'app.text_again', of: ProductInterface::class, params: WithTextAgain::class)]
final class SourceWithTextAgain
{
}
