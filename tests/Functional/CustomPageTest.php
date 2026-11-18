<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Doctrine\DBAL\Connection;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\CustomPage\CustomPage;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\CustomPageKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * A project extends a shipped content type by subclassing its model and overriding it in the plugin's resources
 * config. The subclass keeps the shipped #[ContentType], read from the nearest class that has one. See "Content types"
 * in docs/spec/extending.md
 */
final class CustomPageTest extends WebTestCase
{
    use Entries;

    /**
     * @test
     */
    public function it_keeps_pages_attribute_for_a_subclass_of_page(): void
    {
        self::bootKernel();
        /** @var ContentTypeRegistryInterface $contentTypes */
        $contentTypes = self::getContainer()->get(ContentTypeRegistryInterface::class);

        $page = $contentTypes->get('page');
        self::assertSame(CustomPage::class, $page->modelClass);
        self::assertSame('', $page->prefix);
        self::assertSame('page', $contentTypes->getByEntry(new CustomPage())->code);
    }

    /**
     * @test
     */
    public function it_creates_stores_and_renders_a_page_of_the_subclass_as_a_page(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);

        self::assertInstanceOf(CustomPage::class, $page);
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertSame('page', $connection->fetchOne('SELECT content_type FROM setono_sylius_cms__entry WHERE uuid = ?', [$page->getUuid()]));

        $client->request('GET', '/en_US/about-us');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('.cms-heading', 'Hello');
    }

    /**
     * @param array<array-key, mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new CustomPageKernel('test', (bool) ($_SERVER['APP_DEBUG'] ?? true));
    }
}
