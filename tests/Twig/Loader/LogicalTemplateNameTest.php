<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusCMSPlugin\Twig\Loader;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Setono\SyliusCMSPlugin\Twig\Loader\LogicalTemplateName;

/**
 * @covers \Setono\SyliusCMSPlugin\Twig\Loader\LogicalTemplateName
 */
final class LogicalTemplateNameTest extends TestCase
{
    /**
     * @test
     * @dataProvider getValidLogicalTemplateNames
     */
    public function it_creates_from_string(
        string $logicalTemplateName,
        string $expectedType,
        string $expectedChannelCode,
        string $expectedLocaleCode,
        string $expectedCode
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
    public function it_creates_block_typed(): void
    {
        $logicalTemplateName = LogicalTemplateName::createBlockTyped('FASHION_WEB', 'en_US', 'block1');

        self::assertSame(ElementInterface::TYPE_BLOCK, $logicalTemplateName->type);
        self::assertSame('FASHION_WEB', $logicalTemplateName->channelCode);
        self::assertSame('en_US', $logicalTemplateName->localeCode);
        self::assertSame('block1', $logicalTemplateName->code);
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
        yield ['__sscms/view/FASHION_WEB/en_US/email/send_order', 'view', 'FASHION_WEB', 'en_US', 'email/send_order'];
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
