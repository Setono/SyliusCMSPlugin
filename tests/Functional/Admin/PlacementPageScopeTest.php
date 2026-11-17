<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Setono\SyliusCMSPlugin\Event\PlacementsChanged;
use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Placements;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Each placement's page scope in the Placements section of its snippet's overview page: shown with what it names, and
 * edited with Edit page scope, which offers the condition kinds that read the host page and whose needs the slot
 * provides. See "Placements" in docs/spec/admin.md and "Page scopes" in docs/spec/slots.md
 */
final class PlacementPageScopeTest extends WebTestCase
{
    use Catalog;
    use Entries;
    use Placements;

    /**
     * @test
     */
    public function it_shows_each_placement_s_page_scope_with_the_taxons_and_products_it_names(): void
    {
        $client = self::signedInClient();
        self::createTaxon('SHOES', 'Shoes');
        self::createProduct('T_SHIRT', 'T-shirt');
        $snippet = self::createSnippet('Free returns');
        self::place($snippet, 'shop.footer');
        self::scope(self::place($snippet, 'product.after_price'), [
            self::productInTaxon(['SHOES'], includeChildren: true),
            ['kind' => 'product', 'negate' => true, 'params' => ['products' => ['T_SHIRT']]],
        ]);

        $crawler = $client->request('GET', self::overview($snippet));

        self::assertSame('Page scope', $crawler->filter('[data-section="placements"] th')->eq(1)->text());
        self::assertSame('Every page the slot appears on', $crawler->filter('[data-slot="shop.footer"] [data-page-scope] [data-every-page]')->text());
        self::assertSame(
            ['Product in taxon is one of: Shoes, Include the taxons below them', 'Product is not one of: T-shirt'],
            $crawler->filter('[data-slot="product.after_price"] [data-page-scope-condition]')->each(static fn (Crawler $condition): string => $condition->text()),
        );
    }

    /**
     * The editor is part of the builder app, which the config the page gives it drives
     *
     * @test
     */
    public function it_gives_the_page_scope_editor_of_each_placement_the_kinds_its_slot_provides_the_context_of(): void
    {
        $client = self::signedInClient();
        $snippet = self::createSnippet('Free returns');
        self::place($snippet, 'shop.footer');
        $placement = self::place($snippet, 'product.after_price');

        $crawler = $client->request('GET', self::overview($snippet));

        /** @var array{conditionKinds: list<array{code: string, label: string, needs: list<string>}>, contextKeys: list<array{key: string, label: string}>, endpoints: array{search: string}, locale: string, strings: array<string, string>} $shared */
        $shared = json_decode((string) $crawler->filter('[data-section="placements"]')->attr('data-config'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(['product', 'product_in_taxon', 'taxon', 'entry'], array_column($shared['conditionKinds'], 'code'));
        self::assertSame(['Product', 'Product in taxon', 'Taxon', 'Entry'], array_column($shared['conditionKinds'], 'label'));
        self::assertSame('/admin/cms/api/search/__kind__', $shared['endpoints']['search']);
        self::assertSame('Edit page scope', $shared['strings']['setono_sylius_cms.builder.page_scope.edit']);

        self::assertCount(0, $crawler->filter('[data-slot="shop.footer"] [data-cms-page-scope]'), 'The footer provides no context a kind needs');
        /** @var array{action: string, csrfToken: string, slot: string, scope: list<mixed>, kinds: list<string>} $own */
        $own = json_decode((string) $crawler->filter('[data-slot="product.after_price"] [data-cms-page-scope]')->attr('data-config'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertNotSame('', $own['csrfToken']);
        unset($own['csrfToken']);
        self::assertSame([
            'action' => sprintf('%s/placements/%s/page-scope', self::overview($snippet), $placement->getUuid()),
            'slot' => 'Product page: under the price',
            'scope' => [],
            'kinds' => ['product', 'product_in_taxon'],
        ], $own);
    }

    /**
     * @test
     */
    public function it_saves_a_page_scope_bumps_the_snippet_s_changed_at_and_dispatches_placements_changed(): void
    {
        $client = self::signedInClient();
        $client->disableReboot();
        $snippet = self::createSnippet('Free returns');
        $placement = self::place($snippet, 'product.after_price');
        $changed = [];
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = self::getContainer()->get('event_dispatcher');
        $dispatcher->addListener(PlacementsChanged::class, static function (PlacementsChanged $event) use (&$changed): void {
            $changed[] = [$event->snippetUuids, $event->slots];
        });

        self::postScope($client, $snippet, $placement, (string) json_encode([self::productInTaxon(['SHOES'])]));

        self::assertResponseRedirects(self::overview($snippet));
        $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message.positive', 'The page scope has been saved. The placement shows on the pages it matches at once.');
        self::assertSame([['kind' => 'product_in_taxon', 'negate' => false, 'params' => ['taxons' => ['SHOES'], 'includeChildren' => false]]], self::scopeOf($placement));
        self::assertSame([[[$snippet->getUuid()], ['product.after_price']]], $changed);

        self::postScope($client, $snippet, $placement, '[]');

        self::assertSame([], self::scopeOf($placement), 'An empty scope shows on every page');
    }

    /**
     * @test
     */
    public function it_keeps_the_page_scope_and_says_why_when_its_slot_does_not_provide_what_a_condition_needs(): void
    {
        $client = self::signedInClient();
        $snippet = self::createSnippet('Free returns');
        $placement = self::place($snippet, 'shop.footer');

        self::postScope($client, $snippet, $placement, (string) json_encode([self::productInTaxon(['SHOES']), ['kind' => 'locale', 'negate' => false, 'params' => ['locales' => ['en_US']]]]));

        self::assertResponseRedirects(self::overview($snippet));
        $client->followRedirect();
        self::assertSelectorTextContains(
            '.sylius-flash-message.negative',
            'The page scope wasn\'t saved: Condition 1, Product in taxon, needs the host-page context "Product page", which the slot doesn\'t provide. Condition 2, Locale, doesn\'t read the page, so it can\'t limit a placement. Put it on the snippet\'s blocks instead.',
        );
        self::assertSame([], self::scopeOf($placement));
    }

    /**
     * @test
     */
    public function it_keeps_the_page_scope_and_says_why_when_a_condition_has_no_values(): void
    {
        $client = self::signedInClient();
        $snippet = self::createSnippet('Free returns');
        $placement = self::place($snippet, 'product.after_price');

        self::postScope($client, $snippet, $placement, (string) json_encode([['kind' => 'product', 'negate' => false, 'params' => new \stdClass()]]));
        $client->followRedirect();

        self::assertSelectorTextContains('.sylius-flash-message.negative', 'The page scope wasn\'t saved: Condition 1, Product: Products: This field is required.');
        self::assertSame([], self::scopeOf($placement));
    }

    /**
     * @test
     */
    public function it_keeps_the_page_scope_when_what_is_posted_is_not_a_list_of_conditions(): void
    {
        $client = self::signedInClient();
        $snippet = self::createSnippet('Free returns');
        $placement = self::place($snippet, 'product.after_price');

        self::postScope($client, $snippet, $placement, '{"kind": "product"}');
        $client->followRedirect();

        self::assertSelectorTextContains('.sylius-flash-message.negative', 'The page scope wasn\'t saved. It should be a list of conditions.');
    }

    /**
     * @test
     */
    public function it_refuses_a_page_scope_without_the_page_s_csrf_token_or_for_another_snippet_s_placement(): void
    {
        $client = self::signedInClient();
        $snippet = self::createSnippet('Free returns');
        $placement = self::place($snippet, 'product.after_price');
        $other = self::place(self::createSnippet('Size guide'), 'product.after_price');

        $client->request('POST', sprintf('%s/placements/%s/page-scope', self::overview($snippet), $placement->getUuid()), ['scope' => '[]', '_csrf_token' => 'forged']);
        self::assertResponseStatusCodeSame(403);

        self::postScope($client, $snippet, $other, '[]');
        self::assertResponseStatusCodeSame(404);
    }

    private static function postScope(KernelBrowser $client, SnippetInterface $snippet, PlacementInterface $placement, string $scope): void
    {
        $crawler = $client->request('GET', self::overview($snippet));
        $token = $crawler->filter('[data-section="placements"] form[data-remove] input[name="_csrf_token"]')->attr('value');

        $client->request('POST', sprintf('%s/placements/%s/page-scope', self::overview($snippet), $placement->getUuid()), ['scope' => $scope, '_csrf_token' => $token]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function scopeOf(PlacementInterface $placement): array
    {
        self::entityManager()->clear();

        return self::placements()->findOneByUuid($placement->getUuid())?->getPageScope() ?? [];
    }

    private static function signedInClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        return $client;
    }

    private static function overview(SnippetInterface $snippet): string
    {
        return sprintf('/admin/cms/entry/%s', $snippet->getUuid());
    }
}
