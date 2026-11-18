<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\Snippet;
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

    /**
     * @test
     */
    public function it_goes_to_the_publishing_section_of_the_entrys_overview_page(): void
    {
        $page = new Page();

        self::assertEquals(
            new Fix('setono_sylius_cms.ui.fix_in_publishing', 'setono_sylius_cms_admin_entry_show', ['uuid' => $page->getUuid(), '_fragment' => 'cms-section-publishing']),
            Fix::inPublishing($page),
        );
    }

    /**
     * @test
     */
    public function it_goes_to_the_placements_section_of_the_entrys_overview_page(): void
    {
        $snippet = new Snippet();

        self::assertEquals(
            new Fix('setono_sylius_cms.ui.fix_in_placements', 'setono_sylius_cms_admin_entry_show', ['uuid' => $snippet->getUuid(), '_fragment' => 'cms-section-placements']),
            Fix::inPlacements($snippet),
        );
    }
}
