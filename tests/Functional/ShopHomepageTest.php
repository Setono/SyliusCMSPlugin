<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ShopHomepageTest extends WebTestCase
{
    /**
     * @test
     */
    public function it_shows_the_shop_homepage(): void
    {
        $client = self::createClient();

        $client->request('GET', '/en_US/');

        self::assertResponseStatusCodeSame(200);
    }
}
