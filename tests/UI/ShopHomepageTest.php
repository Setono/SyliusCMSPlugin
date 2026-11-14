<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Symfony\Test\PlaywrightTestCase;
use Setono\SyliusCMSPlugin\Tests\Support\Fixtures;

final class ShopHomepageTest extends PlaywrightTestCase
{
    /**
     * @test
     */
    public function it_shows_the_channel_name_on_the_shop_homepage(): void
    {
        self::bootKernel();
        (new Fixtures(self::getContainer()))->createChannel('Setono CMS Test Store');

        $page = $this->visit('/en_US/');

        self::assertSame('Setono CMS Test Store', $page->title());
    }
}
