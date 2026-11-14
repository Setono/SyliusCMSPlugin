<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Setono\SyliusCMSPlugin\Tests\Support\Fixtures;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ShopHomepageTest extends WebTestCase
{
    /**
     * @test
     */
    public function it_shows_the_shop_homepage(): void
    {
        $client = self::createClient();
        (new Fixtures(self::getContainer()))->createChannel('Setono CMS Test Store');

        $client->request('GET', '/en_US/');

        self::assertResponseStatusCodeSame(200);
    }
}
