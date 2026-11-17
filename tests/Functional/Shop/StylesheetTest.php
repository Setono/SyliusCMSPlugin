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
    public function it_loads_the_stylesheet_in_the_shops_head_before_its_own_styles(): void
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

    /**
     * @test
     */
    public function it_loads_the_carousel_script_in_the_shop_s_body_with_the_labels_of_the_buttons_it_adds(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/en_US/');

        self::assertResponseIsSuccessful();
        $script = $crawler->filter('body script[src="/bundles/setonosyliuscmsplugin/shop/cms.js"]');
        self::assertCount(1, $script, 'The script is in the body');
        self::assertNotNull($script->attr('defer'), 'It runs once the page is parsed');
        self::assertSame(
            ['Previous slide', 'Next slide', 'Slide %number%'],
            [$script->attr('data-cms-label-previous'), $script->attr('data-cms-label-next'), $script->attr('data-cms-label-slide')],
        );
    }
}
