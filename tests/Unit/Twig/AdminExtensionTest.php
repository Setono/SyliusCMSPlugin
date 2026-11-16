<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Twig;

use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Admin\EntryRows;
use Setono\SyliusCMSPlugin\Admin\StatusLabeler;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Repository\DraftRepositoryInterface;
use Setono\SyliusCMSPlugin\Twig\AdminExtension;
use Setono\SyliusCMSPlugin\Twig\AdminRuntime;
use Sylius\Component\Customer\Model\CustomerGroup;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\RuntimeLoader\FactoryRuntimeLoader;
use Twig\Test\IntegrationTestCase;

/**
 * Runs the .test files in AdminFixture, one directory per function. Their --DATA-- sections build the template
 * variables with the public helpers below
 */
final class AdminExtensionTest extends IntegrationTestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<RepositoryInterface<CustomerGroupInterface>> */
    private ObjectProphecy $customerGroups;

    protected function setUp(): void
    {
        /** @var ObjectProphecy<RepositoryInterface<CustomerGroupInterface>> $customerGroups */
        $customerGroups = $this->prophesize(RepositoryInterface::class);
        $this->customerGroups = $customerGroups;
        // A group without a name is named by its code, and one without a code isn't named
        $this->customerGroups->findAll()->willReturn([
            self::customerGroup('wholesale', 'Wholesale'),
            self::customerGroup('retail', null),
            self::customerGroup(null, 'Unfinished'),
        ]);
    }

    /**
     * @test
     */
    public function its_runtime_reads_the_customer_groups_once_until_it_is_reset(): void
    {
        $this->customerGroups->findAll()->willReturn([self::customerGroup('wholesale', 'Wholesale')])->shouldBeCalledTimes(2);
        $runtime = $this->runtime();

        self::assertSame(['wholesale' => 'Wholesale'], $runtime->customerGroupNames());
        self::assertSame(['wholesale' => 'Wholesale'], $runtime->customerGroupNames());
        $runtime->reset();
        self::assertSame(['wholesale' => 'Wholesale'], $runtime->customerGroupNames());
    }

    /**
     * @return array{page: Page}
     */
    public function aPage(string $title): array
    {
        $page = new Page();
        $page->setCurrentLocale('en_US');
        $page->setTitle($title);
        $page->setSlug('about-us');

        return ['page' => $page];
    }

    protected static function getFixturesDirectory(): string
    {
        return __DIR__ . '/AdminFixture';
    }

    /**
     * Twig before 3.13, such as the lowest 3.7 the plugin allows, reads the fixtures from here
     */
    protected function getFixturesDir(): string
    {
        return self::getFixturesDirectory();
    }

    protected function getExtensions(): array
    {
        return [new AdminExtension()];
    }

    /**
     * @return list<FactoryRuntimeLoader>
     */
    protected function getRuntimeLoaders(): array
    {
        $drafts = $this->prophesize(DraftRepositoryInterface::class);
        $drafts->findByEntries(Argument::any())->willReturn([]);
        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator->generate('setono_sylius_cms_shop_entry', ['_locale' => 'en_US', 'path' => 'about-us'])->willReturn('/en_US/about-us');
        $translationLocaleProvider = $this->prophesize(TranslationLocaleProviderInterface::class);
        $translationLocaleProvider->getDefaultLocaleCode()->willReturn('en_US');
        $localeContext = $this->prophesize(LocaleContextInterface::class);
        $localeContext->getLocaleCode()->willReturn('en_US');

        $entryRows = new EntryRows(
            $drafts->reveal(),
            self::contentTypes(),
            new StatusLabeler(new MockClock()),
            $urlGenerator->reveal(),
            $translationLocaleProvider->reveal(),
            $localeContext->reveal(),
        );
        $runtime = $this->runtime();

        return [new FactoryRuntimeLoader([
            EntryRows::class => static fn (): EntryRows => $entryRows,
            AdminRuntime::class => static fn (): AdminRuntime => $runtime,
        ])];
    }

    private function runtime(): AdminRuntime
    {
        return new AdminRuntime(self::contentTypes(), $this->customerGroups->reveal());
    }

    private static function contentTypes(): ContentTypeRegistry
    {
        return new ContentTypeRegistry(['page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page']]);
    }

    private static function customerGroup(?string $code, ?string $name): CustomerGroupInterface
    {
        $group = new CustomerGroup();
        $group->setCode($code);
        $group->setName($name);

        return $group;
    }
}
