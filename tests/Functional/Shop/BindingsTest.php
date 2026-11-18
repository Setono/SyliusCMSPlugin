<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Problem\ProblemCount;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;
use Setono\SyliusCMSPlugin\Tests\Support\Customers;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Placements;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Bindings show a property of the host page's object, the channel or the customer in a field, in a link's ref and as a
 * token in rich text. See "Bindings" in docs/spec/slots.md and "Hydration" in docs/spec/rendering.md
 */
final class BindingsTest extends WebTestCase
{
    use Catalog;
    use Customers;
    use Entries;
    use Placements;

    private const HEADING = '0193a5be-0000-7000-8000-000000000001';

    private const BUTTON = '0193a5be-0000-7000-8000-000000000002';

    private const RICH_TEXT = '0193a5be-0000-7000-8000-000000000003';

    /**
     * @test
     */
    public function it_shows_the_product_s_name_in_a_heading_its_url_in_a_button_and_its_name_in_rich_text_on_each_product_s_page(): void
    {
        $client = self::createClient();
        self::createProduct('T_SHIRT', 'T-shirt');
        self::createProduct('MUG', 'Mug');
        $snippet = self::createSnippet('Product intro', [
            new StoredBlock(self::HEADING, 'heading', 1, fields: ['text' => self::binding('product.name', ['en_US' => 'This product'])]),
            new StoredBlock(self::BUTTON, 'button', 1, fields: ['link' => ['target' => 'url', 'ref' => self::binding('product.url'), 'anchor' => 'reviews', 'label' => ['en_US' => 'Read the reviews']]]),
            self::richText(['type' => 'text', 'text' => 'Buy '], self::token('product.name', 'it'), ['type' => 'text', 'text' => ' today']),
        ], published: true, context: ['product']);
        self::place($snippet, 'product.after_price');

        foreach (['t-shirt' => 'T-shirt', 'mug' => 'Mug'] as $slug => $name) {
            $crawler = $client->request('GET', '/en_US/products/' . $slug);
            self::assertResponseIsSuccessful();

            $slot = $crawler->filter('.cms-slot--product-after-price');
            self::assertSame($name, $slot->filter('.cms-heading')->text());
            self::assertSame(sprintf('/en_US/products/%s#reviews', $slug), $slot->filter('a.cms-button')->attr('href'));
            self::assertSame('Read the reviews', $slot->filter('a.cms-button')->text());
            self::assertSame(sprintf('Buy %s today', $name), $slot->filter('.cms-rich-text')->text());
        }
    }

    /**
     * Publish can't check a value that depends on the page, so it's checked when it renders, as an empty one is
     *
     * @test
     */
    public function it_shows_the_fallback_where_the_bound_value_fails_the_field_s_own_constraints(): void
    {
        $client = self::createClient();
        self::createProduct('T_SHIRT', 'T-shirt');
        $snippet = self::createSnippet('Product video', [
            new StoredBlock(self::HEADING, 'video', 1, fields: ['embedUrl' => self::binding('product.name', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')]),
        ], published: true, context: ['product']);
        self::place($snippet, 'product.after_price');

        $crawler = $client->request('GET', '/en_US/products/t-shirt');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('dQw4w9WgXcQ', (string) $crawler->filter('.cms-slot--product-after-price .cms-video iframe')->attr('src'), 'T-shirt isn\'t a YouTube or Vimeo URL');
    }

    /**
     * @test
     */
    public function it_shows_the_fallback_of_a_customer_binding_to_a_guest_and_the_customer_s_own_value_once_signed_in(): void
    {
        $client = self::createClient();
        $snippet = self::createSnippet('Greeting', [
            new StoredBlock(self::HEADING, 'heading', 1, fields: ['text' => self::binding('customer.first_name', ['en_US' => 'Hi there'])]),
            self::richText(['type' => 'text', 'text' => 'Welcome, '], self::token('customer.first_name', 'friend')),
        ], published: true);
        self::place($snippet, 'shop.footer');

        $crawler = $client->request('GET', '/en_US/');
        self::assertResponseIsSuccessful();
        self::assertSame('Hi there', $crawler->filter('.cms-slot--shop-footer .cms-heading')->text());
        self::assertSame('Welcome, friend', $crawler->filter('.cms-slot--shop-footer .cms-rich-text')->text());

        $user = self::createShopUser('ada@example.com');
        $customer = $user->getCustomer();
        self::assertInstanceOf(CustomerInterface::class, $customer);
        $customer->setFirstName('Ada');
        self::entityManager()->flush();
        $client->loginUser($user, 'shop');

        $crawler = $client->request('GET', '/en_US/');
        self::assertSame('Ada', $crawler->filter('.cms-slot--shop-footer .cms-heading')->text());
        self::assertSame('Welcome, Ada', $crawler->filter('.cms-slot--shop-footer .cms-rich-text')->text());
    }

    /**
     * @test
     */
    public function it_leaves_a_bound_field_without_a_fallback_empty_where_its_value_is_empty(): void
    {
        $client = self::createClient();
        self::createProduct('T_SHIRT', 'T-shirt');
        $snippet = self::createSnippet('Original price', [
            new StoredBlock(self::HEADING, 'heading', 1, fields: ['text' => self::binding('product.original_price')]),
        ], published: true, context: ['product']);
        self::place($snippet, 'product.after_price');

        $crawler = $client->request('GET', '/en_US/products/t-shirt');

        self::assertResponseIsSuccessful();
        self::assertSame([''], $crawler->filter('.cms-slot--product-after-price .cms-heading')->each(static fn (Crawler $heading): string => $heading->text()));
    }

    /**
     * An entry the CMS renders provides itself as the entry context key
     *
     * @test
     */
    public function it_shows_the_page_s_own_title_and_the_channel_s_name_in_a_page(): void
    {
        $client = self::createClient();
        self::createPageWithBlocks('About us', 'about-us', [
            new StoredBlock(self::HEADING, 'heading', 1, fields: ['text' => self::binding('entry.title')]),
            self::richText(['type' => 'text', 'text' => 'Welcome to '], self::token('channel.name', 'our shop')),
        ], published: true);

        $crawler = $client->request('GET', '/en_US/about-us');

        self::assertResponseIsSuccessful();
        self::assertSame('About us', $crawler->filter('.cms-tree .cms-heading')->text());
        self::assertSame('Welcome to Setono CMS Test Store', $crawler->filter('.cms-tree .cms-rich-text')->text());
    }

    /**
     * Such as one a deploy removed. Publish refuses it, so it's in content published before
     *
     * @test
     */
    public function it_shows_the_fallback_of_a_binding_of_a_property_the_code_no_longer_has_and_flags_it(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $page = self::createPageWithBlocks('About us', 'about-us', [
            new StoredBlock(self::HEADING, 'heading', 1, fields: ['text' => self::binding('channel.slogan', ['en_US' => 'Welcome'])]),
        ], published: true);

        self::assertSame('Welcome', $client->request('GET', '/en_US/about-us')->filter('.cms-tree .cms-heading')->text());

        self::assertEquals(new ProblemCount(0, 1), self::storedCount($page));
        $problems = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()))->filter('[data-section="problems"] [data-problem]');
        self::assertSame(
            [['unknown_bindable_property', 'warning', self::HEADING, 'Text in the Heading block is bound to "channel.slogan", which the code no longer has, so it shows its fallback.']],
            $problems->each(static fn (Crawler $problem): array => [$problem->attr('data-problem'), $problem->attr('data-severity'), $problem->attr('data-block'), $problem->filter('.description')->text()]),
        );
    }

    /**
     * @return array{'$bind': string, '$params': array<string, mixed>, '$fallback': mixed}
     */
    private static function binding(string $property, mixed $fallback = null): array
    {
        return ['$bind' => $property, '$params' => [], '$fallback' => $fallback];
    }

    /**
     * @return array{type: 'cmsBinding', attrs: array{property: string, params: array<string, mixed>, fallback: string|null}}
     */
    private static function token(string $property, ?string $fallback = null): array
    {
        return ['type' => 'cmsBinding', 'attrs' => ['property' => $property, 'params' => [], 'fallback' => $fallback]];
    }

    /**
     * @param array<string, mixed> ...$content
     */
    private static function richText(array ...$content): StoredBlock
    {
        return new StoredBlock(self::RICH_TEXT, 'rich_text', 1, fields: ['content' => ['en_US' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => array_values($content)]]]]]);
    }
}
