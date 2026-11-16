<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\PagePrefixKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * A project moves a content type's URLs by setting its prefix in setono_sylius_cms.content_types. The old URLs don't
 * redirect. See "URL shape" in docs/spec/urls-and-seo.md
 */
final class UrlPrefixTest extends WebTestCase
{
    use Entries;

    protected static function getKernelClass(): string
    {
        return PagePrefixKernel::class;
    }

    /**
     * @test
     */
    public function it_moves_the_url_without_a_redirect_when_a_prefix_is_set_in_config(): void
    {
        $client = self::createClient();
        self::createPage('About us', 'about-us', 'Hello', published: true);

        $client->request('GET', '/en_US/pg/about-us');
        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextSame('.cms-heading', 'Hello');

        $client->request('GET', '/en_US/about-us');
        self::assertResponseStatusCodeSame(404);
        self::assertFalse($client->getRequest()->attributes->has('_route'), 'The URL without the prefix is no URL of the page any more');
    }
}
