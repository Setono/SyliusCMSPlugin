<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

final class ShopHomepageTest extends UITestCase
{
    /**
     * @test
     */
    public function it_shows_the_channel_name_on_the_shop_homepage(): void
    {
        $page = $this->visit('/en_US/');

        self::assertSame('Setono CMS Test Store', $page->title());
    }
}
