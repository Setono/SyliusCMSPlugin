<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Preview\Preview;
use Setono\SyliusCMSPlugin\Preview\PreviewTokenSigner;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\GreetingKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Admins;

/**
 * preview_render runs on an admin URL, in the admin's own locale, but renders blocks in the preview's locale, as a full
 * reload of the preview does. See "Updating the preview" in docs/spec/builder.md
 */
final class PreviewRenderLocaleTest extends BuilderApiTestCase
{
    use Admins;

    private const GREETING = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    protected static function getKernelClass(): string
    {
        return GreetingKernel::class;
    }

    /**
     * @test
     */
    public function it_renders_blocks_in_the_previews_locale_for_an_admin_who_reads_the_admin_in_another(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us');
        $csrfToken = self::openBuilder($client, $page, self::createAdmin('jane', 'Jane', 'Roe', 'da_DK'));

        $response = self::json($client, 'POST', '/admin/cms/api/preview/render?_cms_preview=' . self::token($page), ['blocks' => [[
            'key' => self::GREETING, 'type' => 'greeting', 'version' => 1, 'style' => null, 'fields' => [], 'translated' => [], 'conditions' => [], 'children' => [],
        ]]], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertIsArray($response['blocks'] ?? null);
        self::assertIsString($response['blocks'][self::GREETING] ?? null);
        self::assertStringContainsString(
            '<p class="app-greeting" data-request-locale="en_US" data-sylius-locale="en_US">Hello</p>',
            $response['blocks'][self::GREETING],
        );
    }

    private static function token(PageInterface $page): string
    {
        /** @var PreviewTokenSigner $signer */
        $signer = self::getContainer()->get(PreviewTokenSigner::class);

        return $signer->sign(new Preview($page->getUuid(), 'WEB', 'en_US'));
    }
}
