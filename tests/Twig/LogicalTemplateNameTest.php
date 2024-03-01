<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Twig;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Setono\SyliusCMSPlugin\Twig\LogicalTemplateName;

/**
 * @covers \Setono\SyliusCMSPlugin\Twig\LogicalTemplateName
 */
final class LogicalTemplateNameTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider getValidLogicalTemplateNames
     */
    public function it_creates_from_string(
        string $logicalTemplateName,
        string $expectedType,
        string $expectedChannelCode,
        string $expectedLocaleCode,
        string $expectedCode,
    ): void {
        $logicalTemplateName = LogicalTemplateName::createFromString($logicalTemplateName);

        self::assertSame($expectedType, $logicalTemplateName->type);
        self::assertSame($expectedChannelCode, $logicalTemplateName->channelCode);
        self::assertSame($expectedLocaleCode, $logicalTemplateName->localeCode);
        self::assertSame($expectedCode, $logicalTemplateName->code);
    }

    /**
     * @test
     */
    public function it_casts_to_string(): void
    {
        $logicalTemplateName = new LogicalTemplateName(ElementInterface::TYPE_BLOCK, 'FASHION_WEB', 'en_US', 'block1');

        self::assertSame('__sscms/block/FASHION_WEB/en_US/block1', (string) $logicalTemplateName);
    }

    /**
     * @test
     *
     * @dataProvider getInvalidLogicalTemplateNames
     */
    public function it_throws_if_template_name_is_malformed(string $logicalTemplateName): void
    {
        $this->expectException(\InvalidArgumentException::class);

        LogicalTemplateName::createFromString($logicalTemplateName);
    }

    /**
     * @return \Generator<int, array<array-key, string>>
     */
    public function getValidLogicalTemplateNames(): \Generator
    {
        yield ['__sscms/block/FASHION_WEB/en_US/block1', 'block', 'FASHION_WEB', 'en_US', 'block1'];
    }

    /**
     * @return \Generator<int, array<array-key, string>>
     */
    public function getInvalidLogicalTemplateNames(): \Generator
    {
        yield ['__sscmss/block/FASHION_WEB/en_US/block1'];
        yield ['__sscmss/block//en_US/block1'];
    }
}
