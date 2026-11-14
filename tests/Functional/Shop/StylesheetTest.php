<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * See "Loading the stylesheet and script" in docs/spec/blocks.md. SwitchingOffWhatShipsTest covers setono_sylius_cms.assets: false
 */
final class StylesheetTest extends WebTestCase
{
    /**
     * @test
     */
    public function the_shop_loads_the_stylesheet_in_its_head_before_its_own_styles(): void
    {
        $client = self::createClient();

        $client->request('GET', '/en_US/');

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        $head = substr($html, 0, (int) strpos($html, '</head>'));

        $cms = strpos($head, '<link rel="stylesheet" href="/bundles/setonosyliuscmsplugin/shop/cms.css">');
        // The test application's shop styles block prints its Encore build, under /build/shop
        $shop = strpos($head, '/build/shop/');

        self::assertIsInt($cms, 'The stylesheet is in the head');
        self::assertIsInt($shop);
        self::assertLessThan($shop, $cms, 'The stylesheet loads before the shop\'s own styles, so it declares the cms layer before the theme does');
    }
}
