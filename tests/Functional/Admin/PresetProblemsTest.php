<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\BrokenPresetsKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * A preset with blocks of removed types is a warning problem: the Overview's Needs attention lists it by its name,
 * marked as coming from code, and the Content badge counts it. See "Problems" in docs/spec/admin.md and "Presets with
 * invalid blocks" in docs/spec/layouts-and-presets.md
 */
final class PresetProblemsTest extends WebTestCase
{
    use Entries;

    protected static function getKernelClass(): string
    {
        return BrokenPresetsKernel::class;
    }

    /**
     * @test
     */
    public function it_lists_a_preset_with_blocks_of_removed_types_under_needs_attention_and_counts_it_in_the_content_badge(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        $crawler = $client->request('GET', '/admin/cms');

        self::assertResponseIsSuccessful();
        $panel = $crawler->filter('[data-panel="needs_attention"]');
        self::assertSame([
            ['preset_removed_block_type', 'warning', 'Presets with blocks of removed types 1', [
                ['retired', 'Retired gallery From code', 'It has blocks of the type "gallery", which the code no longer has, so inserting it leaves them out.'],
            ]],
        ], $panel->filter('[data-problem-kind]')->each(static fn (Crawler $group): array => [
            $group->attr('data-problem-kind'),
            $group->attr('data-severity'),
            $group->filter('h3')->text(),
            $group->filter('[data-preset]')->each(static fn (Crawler $problem): array => [
                $problem->attr('data-preset'),
                $problem->filter('.header')->text(),
                $problem->filter('.description')->text(),
            ]),
        ]));

        $content = $crawler->filter('.sylius-admin-menu a[href="/admin/cms"]');
        self::assertSame('1', $content->attr('data-cms-problem-count'));
        self::assertSame('warning', $content->attr('data-cms-problems'));
    }
}
