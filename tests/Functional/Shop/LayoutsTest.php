<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\DisabledLayoutKernel;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\TwoDefaultLayoutsKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * An entry renders in its layout's template, with its layout's fields. See "Layouts" in docs/spec/layouts-and-presets.md
 */
final class LayoutsTest extends WebTestCase
{
    use Entries;

    /** @var class-string<KernelInterface>|null the fixture kernel a test boots instead of the test application */
    private static ?string $fixtureKernel = null;

    protected function tearDown(): void
    {
        parent::tearDown();

        self::$fixtureKernel = null;
    }

    /**
     * @test
     */
    public function a_page_renders_in_the_standard_layout_by_default_with_its_title_and_then_its_tree(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);

        $crawler = $client->request('GET', '/en_US/about-us');

        self::assertResponseIsSuccessful();
        self::assertSame('standard', $page->getBlockTree()->layout?->code, 'A new page starts in its content type\'s default layout');
        self::assertSame('About us', trim($crawler->filter('h1')->text()));
        self::assertSelectorTextSame('.cms-tree > .cms-heading', 'Hello');
    }

    /**
     * @test
     */
    public function show_title_off_hides_the_title(): void
    {
        $client = self::createClient();
        self::inLayout(self::createPage('About us', 'about-us', 'Hello', published: true), new StoredLayout('standard', 1, ['showTitle' => false]));

        $client->request('GET', '/en_US/about-us');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('h1');
        self::assertSelectorTextSame('.cms-tree > .cms-heading', 'Hello');
    }

    /**
     * @test
     */
    public function a_page_renders_in_the_layout_its_tree_names_with_that_layouts_fields(): void
    {
        $client = self::createClient();
        self::inLayout(self::createPage('About us', 'about-us', 'Hello', published: true), new StoredLayout('landing', 1, ['tagline' => ['en_US' => 'Big news'], 'transparentHeader' => true]));

        $client->request('GET', '/en_US/about-us');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('.app-landing.app-landing--transparent .app-landing__tagline', 'Big news');
        self::assertSelectorNotExists('h1', 'The test application\'s layout doesn\'t print the title');
        self::assertSelectorTextSame('.cms-tree > .cms-heading', 'Hello');
    }

    /**
     * @test
     */
    public function a_page_whose_layout_was_removed_from_code_renders_in_the_default_layout_ignoring_the_stored_values(): void
    {
        $client = self::createClient();
        self::inLayout(self::createPage('About us', 'about-us', 'Hello', published: true), new StoredLayout('gone', 1, ['showTitle' => false]));

        $client->request('GET', '/en_US/about-us');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'About us');
        self::assertSelectorTextSame('.cms-tree > .cms-heading', 'Hello');
    }

    /**
     * @test
     */
    public function a_page_without_a_layout_in_its_tree_renders_in_the_default_layout(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        $page->setBlockTree(new StoredTree(null, $page->getBlockTree()->blocks));
        self::flush();

        $client->request('GET', '/en_US/about-us');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'About us');
    }

    /**
     * @test
     */
    public function a_page_whose_layout_is_disabled_renders_in_the_default_layout(): void
    {
        self::$fixtureKernel = DisabledLayoutKernel::class;
        $client = self::createClient();
        self::inLayout(self::createPage('About us', 'about-us', 'Hello', published: true), new StoredLayout('landing', 1, ['tagline' => ['en_US' => 'Big news']]));

        $client->request('GET', '/en_US/about-us');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'About us');
        self::assertSelectorNotExists('.app-landing');
    }

    /**
     * @test
     */
    public function two_default_layouts_of_one_content_type_fail_the_build(): void
    {
        self::$fixtureKernel = TwoDefaultLayoutsKernel::class;

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The content type "page" has more than one default layout');

        self::bootKernel();
    }

    /**
     * @param array<array-key, mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return null === self::$fixtureKernel ? parent::createKernel($options) : new (self::$fixtureKernel)('test', (bool) ($_SERVER['APP_DEBUG'] ?? true));
    }

    private static function inLayout(PageInterface $page, StoredLayout $layout): void
    {
        $page->setBlockTree(new StoredTree($layout, $page->getBlockTree()->blocks));
        self::flush();
    }

    private static function flush(): void
    {
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->flush();
    }
}
