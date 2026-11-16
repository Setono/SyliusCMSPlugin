<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Problem\Fix;

final class FixTest extends TestCase
{
    /**
     * @test
     */
    public function it_opens_the_entry_in_the_builder_at_the_block_and_in_the_locale_when_they_are_given(): void
    {
        $page = new Page();
        $uuid = $page->getUuid();

        self::assertEquals(new Fix('setono_sylius_cms.ui.fix_in_builder', 'setono_sylius_cms_admin_builder', ['uuid' => $uuid]), Fix::inBuilder($page));
        self::assertSame(['uuid' => $uuid, 'block' => 'abc'], Fix::inBuilder($page, 'abc')->parameters);
        self::assertSame(['uuid' => $uuid, 'locale' => 'da_DK'], Fix::inBuilder($page, null, 'da_DK')->parameters);
        self::assertSame(['uuid' => $uuid, 'block' => 'abc', 'locale' => 'da_DK'], Fix::inBuilder($page, 'abc', 'da_DK')->parameters);
    }
}
