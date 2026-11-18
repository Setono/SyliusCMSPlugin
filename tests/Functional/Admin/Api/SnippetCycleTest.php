<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Blocks;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Publish refuses a snippet whose Snippet blocks lead back to it, directly or through the published trees of the
 * snippets they show, and the builder's validation flags it at the block, naming the way. See "Validation" in
 * docs/spec/publishing.md and "Snippet" in docs/spec/blocks.md
 */
final class SnippetCycleTest extends BuilderApiTestCase
{
    private const HEADING = '0193a5be-0000-7000-8000-0000000000a1';

    private const SHOWS_FOOTER = '0193a5be-0000-7000-8000-0000000000a2';

    private const SHOWS_PROMO = '0193a5be-0000-7000-8000-0000000000a3';

    private const REFUSED = 'It would show this snippet inside itself: Footer → Promo → Footer. Pick another snippet.';

    /**
     * @test
     */
    public function it_refuses_to_publish_a_snippet_block_that_leads_back_to_its_snippet_through_another_snippet_naming_the_way(): void
    {
        $client = self::createClient();
        [$footer, $promo] = self::footerShownByPromo();
        $rows = $footer->getBlockTreeJson();
        $csrfToken = self::showPromoInFooter($client, $footer, $promo);

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $footer->getUuid()), null, $csrfToken);

        self::assertResponseStatusCodeSame(422);
        self::assertIsArray($response);
        self::assertSame([['block' => self::SHOWS_PROMO, 'field' => 'snippet', 'locale' => null, 'message' => self::REFUSED]], $response['errors'] ?? null);
        self::entityManager()->clear();
        $reloaded = self::entityManager()->getRepository(SnippetInterface::class)->findOneBy(['uuid' => $footer->getUuid()]);
        self::assertInstanceOf(SnippetInterface::class, $reloaded);
        self::assertSame($rows, $reloaded->getBlockTreeJson(), 'Nothing was published');
    }

    /**
     * @test
     */
    public function it_flags_a_snippet_block_that_leads_back_to_its_snippet_in_the_builders_validation(): void
    {
        $client = self::createClient();
        [$footer, $promo] = self::footerShownByPromo();
        self::showPromoInFooter($client, $footer, $promo);

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/validate', $footer->getUuid()));

        self::assertResponseIsSuccessful();
        self::assertSame(['errors' => [['block' => self::SHOWS_PROMO, 'field' => 'snippet', 'locale' => null, 'message' => self::REFUSED]]], $response);
    }

    /**
     * Promo shows Footer only in its draft, which a Snippet block doesn't render
     *
     * @test
     */
    public function it_publishes_a_snippet_block_whose_snippet_shows_it_only_in_its_draft(): void
    {
        $client = self::createClient();
        $footer = self::createSnippet('Footer', [self::heading(self::HEADING, 'Footer')], published: true);
        $promo = self::createSnippet('Promo', published: true);
        self::saveChanges($promo, [['op' => 'add_block', 'parent' => null, 'index' => 0, 'block' => ['key' => self::SHOWS_FOOTER, 'type' => 'snippet', 'version' => 1, 'fields' => ['snippet' => $footer->getUuid()]]]]);
        $csrfToken = self::showPromoInFooter($client, $footer, $promo);

        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $footer->getUuid()), null, $csrfToken);

        self::assertResponseIsSuccessful();
    }

    /**
     * Footer, published with a heading, and Promo, published showing Footer
     *
     * @return array{SnippetInterface, SnippetInterface}
     */
    private static function footerShownByPromo(): array
    {
        $footer = self::createSnippet('Footer', [self::heading(self::HEADING, 'Footer')], published: true);
        $promo = self::createSnippet('Promo', [Blocks::snippet(self::SHOWS_FOOTER, $footer)], published: true);

        return [$footer, $promo];
    }

    /**
     * Adds a Snippet block showing Promo to Footer's draft in the builder, and gives the builder's CSRF token
     */
    private static function showPromoInFooter(KernelBrowser $client, SnippetInterface $footer, SnippetInterface $promo): string
    {
        $csrfToken = self::openBuilder($client, $footer);
        self::json($client, 'PATCH', '/admin/cms/api/entries/' . $footer->getUuid(), ['lockToken' => 0, 'changes' => [
            ['op' => 'add_block', 'parent' => null, 'index' => 1, 'block' => ['key' => self::SHOWS_PROMO, 'type' => 'snippet', 'version' => 1, 'fields' => ['snippet' => $promo->getUuid()]]],
        ]], $csrfToken);
        self::assertResponseIsSuccessful();

        return $csrfToken;
    }
}
