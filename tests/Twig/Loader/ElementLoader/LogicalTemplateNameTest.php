<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusCMSPlugin\Twig\Loader\ElementLoader;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Setono\SyliusCMSPlugin\Twig\Loader\ElementLoader\LogicalTemplateName;

/**
 * @covers \Setono\SyliusCMSPlugin\Twig\Loader\ElementLoader\LogicalTemplateName
 */
final class LogicalTemplateNameTest extends TestCase
{
    /**
     * @test
     */
    public function it_creates_from_string(): void
    {
        $str = '__sscms/block/FASHION_WEB/en_US/block1';

        $logicalTemplateName = LogicalTemplateName::createFromString($str);

        self::assertSame(ElementInterface::TYPE_BLOCK, $logicalTemplateName->type);
        self::assertSame('FASHION_WEB', $logicalTemplateName->channelCode);
        self::assertSame('en_US', $logicalTemplateName->localeCode);
        self::assertSame('block1', $logicalTemplateName->code);
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
     */
    public function it_throws_if_template_name_is_malformed(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $str = '__sscmss/block/FASHION_WEB/en_US/block1';
        LogicalTemplateName::createFromString($str);
    }
}
