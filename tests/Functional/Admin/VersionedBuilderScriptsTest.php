<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\VersionedAssetsKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The scripts of field controls (setono_sylius_cms.builder.scripts) are resolved through Symfony's asset(), so a
 * project whose build versions its files gets the built file's URL from its manifest. See "Configuration" in
 * docs/spec/extending.md
 */
final class VersionedBuilderScriptsTest extends WebTestCase
{
    use Entries;

    protected static function getKernelClass(): string
    {
        return VersionedAssetsKernel::class;
    }

    /**
     * @test
     */
    public function it_loads_a_field_control_script_from_the_url_its_asset_package_s_manifest_gives(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $page = self::createPage('About us', 'about-us');

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['/cms/opening-hours.3f2a1b9c.js', '/bundles/setonosyliuscmsplugin/builder/builder.js'],
            $crawler->filter('script[type="module"]')->each(static fn (Crawler $script): ?string => $script->attr('src')),
        );
    }
}
