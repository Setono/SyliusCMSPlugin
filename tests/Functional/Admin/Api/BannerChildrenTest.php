<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * A Banner holds only Buttons: entry_validate, and so publish, refuses any other block inside one. See "Banner" and the
 * block types' children in docs/spec/blocks.md
 */
final class BannerChildrenTest extends BuilderApiTestCase
{
    private const BANNER = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e70';

    private const BUTTON = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e71';

    private const HEADING = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e72';

    /**
     * @test
     */
    public function it_takes_buttons_in_a_banner(): void
    {
        $client = self::createClient();
        $page = self::createPageWithBlocks('Summer', 'summer', [
            new StoredBlock(self::BANNER, 'banner', 1, fields: ['heading' => ['en_US' => 'Summer sale']], children: [self::button()]),
        ]);
        self::openBuilder($client, $page);

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/validate', $page->getUuid()));

        self::assertResponseIsSuccessful();
        self::assertSame(['errors' => []], $response);
    }

    /**
     * @test
     */
    public function it_refuses_any_other_block_in_a_banner(): void
    {
        $client = self::createClient();
        $page = self::createPageWithBlocks('Summer', 'summer', [
            new StoredBlock(self::BANNER, 'banner', 1, children: [self::button(), self::heading(self::HEADING, 'Up to 50% off')]),
        ]);
        self::openBuilder($client, $page);

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/validate', $page->getUuid()));

        self::assertSame(['errors' => [
            ['block' => self::HEADING, 'field' => null, 'locale' => null, 'message' => 'Heading can\'t go here.'],
        ]], $response);
    }

    private static function button(): StoredBlock
    {
        return new StoredBlock(self::BUTTON, 'button', 1, fields: [
            'link' => ['target' => 'url', 'ref' => ['en_US' => '/en_US/sale'], 'anchor' => null, 'label' => ['en_US' => 'Shop now']],
        ]);
    }
}
