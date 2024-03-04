<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Stack;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Stack\RenderedElement;
use Setono\SyliusCMSPlugin\Twig\LogicalTemplateName;

final class RenderedElementTest extends TestCase
{
    /**
     * @test
     */
    public function it_creates_from_logical_template_name(): void
    {
        $logicalTemplateName = new LogicalTemplateName('type', 'channelCode', 'localeCode', 'code');
        $renderedElement = RenderedElement::fromLogicalTemplateName($logicalTemplateName);
        self::assertSame('type', $renderedElement->type);
        self::assertSame('code', $renderedElement->code);
    }
}
