<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Preview\Preview;
use Setono\SyliusCMSPlugin\Preview\PreviewTokenSigner;
use Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api\BuilderApiTestCase;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\BrokenBlockKernel;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * A block that throws while rendering in the builder's preview shows its error on that block, in dev and in production
 * alike, and the rest of the preview still renders. See "Failures" in docs/spec/rendering.md
 */
final class PreviewFailureTest extends BuilderApiTestCase
{
    private const HEADING = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    private const BROKEN = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e51';

    private const ERROR = 'Setono\SyliusCMSPlugin\Tests\Functional\Fixture\Broken has no field "missing"';

    protected static function getKernelClass(): string
    {
        return BrokenBlockKernel::class;
    }

    /**
     * @test
     */
    public function preview_render_answers_a_throwing_blocks_error_under_its_key_and_html_for_the_others(): void
    {
        $client = self::createClient();
        $page = self::createPageWithBlocks('About us', 'about-us', []);
        $csrfToken = self::openBuilder($client, $page);

        $response = self::json($client, 'POST', '/admin/cms/api/preview/render?_cms_preview=' . self::token($page), ['blocks' => [
            self::stored(self::BROKEN, 'broken'),
            self::stored(self::HEADING, 'heading', ['text' => ['en_US' => 'Still here']]),
        ]], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertIsArray($response['blocks'] ?? null);
        self::assertStringStartsWith('<!--cms:block ' . self::BROKEN . ' broken--><div data-cms-block-error role="alert"', (string) $response['blocks'][self::BROKEN]);
        self::assertStringContainsString('>' . htmlspecialchars(self::ERROR) . '</div><!--/cms:block ' . self::BROKEN . '-->', (string) $response['blocks'][self::BROKEN]);
        self::assertStringNotContainsString('cms-broken', (string) $response['blocks'][self::BROKEN], 'No half-printed markup is left behind');
        self::assertMatchesRegularExpression('#<h2 class="cms-heading">Still here</h2>#', (string) $response['blocks'][self::HEADING]);
    }

    /**
     * @test
     */
    public function the_preview_shows_a_throwing_blocks_error_on_it_and_renders_the_rest(): void
    {
        $client = self::createClient();
        $broken = new StoredBlock(self::BROKEN, 'broken', 1);
        $page = self::createPageWithBlocks('About us', 'about-us', [self::heading(self::HEADING, 'Still here'), $broken]);

        $client->request('GET', '/en_US/about-us?_cms_preview=' . self::token($page));

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextSame('.cms-tree .cms-heading', 'Still here');
        self::assertSelectorTextSame('.cms-tree [data-cms-block-error]', self::ERROR);
        self::assertSelectorNotExists('.cms-broken');
        self::assertSelectorExists('#footer');
    }

    private static function token(PageInterface $page): string
    {
        /** @var PreviewTokenSigner $signer */
        $signer = self::getContainer()->get(PreviewTokenSigner::class);

        return $signer->sign(new Preview($page->getUuid(), 'WEB', 'en_US'));
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    private static function stored(string $key, string $type, array $fields = []): array
    {
        return ['key' => $key, 'type' => $type, 'version' => 1, 'style' => null, 'fields' => (object) $fields, 'translated' => (object) [], 'conditions' => [], 'children' => []];
    }
}
