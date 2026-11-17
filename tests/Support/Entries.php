<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSet;
use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Model\PageTranslationInterface;
use Setono\SyliusCMSPlugin\Preview\Preview;
use Setono\SyliusCMSPlugin\Preview\PreviewTokenSigner;
use Setono\SyliusCMSPlugin\Problem\ProblemCount;
use Setono\SyliusCMSPlugin\Problem\ProblemCountProviderInterface;
use Setono\SyliusCMSPlugin\Publishing\AutosaveInterface;
use Setono\SyliusCMSPlugin\Publishing\EntryPublisherInterface;
use Setono\SyliusCMSPlugin\Publishing\EntrySettings;
use Setono\SyliusCMSPlugin\Publishing\EntrySettingsSaverInterface;
use Setono\SyliusCMSPlugin\Publishing\SaveResult;
use Setono\SyliusCMSPlugin\Repository\DraftRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\SavedChangeSetRepositoryInterface;
use Setono\SyliusCMSPlugin\Targeting\ChannelsAndAudienceModifierInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Helpers for functional and UI tests. The data they build on (a channel, its locale and currency, and the admin user
 * "sylius") is the test application's "test" fixtures suite, loaded once before the suites run
 */
trait Entries
{
    abstract protected static function getContainer(): ContainerInterface;

    /**
     * Creates a page in en_US through the page resource's own factory, with a heading block when $heading is given.
     * Like any new entry, it's in every channel unless $channels says otherwise
     *
     * @param list<string>|null $channels channel codes
     */
    protected static function createPage(string $title, string $slug, ?string $heading = null, bool $published = false, ?array $channels = null): PageInterface
    {
        return self::createPageWithBlocks($title, $slug, null === $heading ? [] : [self::heading('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', $heading)], $published, $channels);
    }

    /**
     * Creates a page in en_US through the page resource's own factory, with these top-level blocks.
     * Like any new entry, it's in every channel unless $channels says otherwise
     *
     * @param list<StoredBlock> $blocks
     * @param list<string>|null $channels channel codes
     */
    protected static function createPageWithBlocks(string $title, string $slug, array $blocks, bool $published = false, ?array $channels = null): PageInterface
    {
        /** @var FactoryInterface<PageInterface> $factory */
        $factory = self::getContainer()->get('setono_sylius_cms.factory.page');

        $page = $factory->createNew();
        $page->setCurrentLocale('en_US');
        $page->setFallbackLocale('en_US');
        $page->setTitle($title);
        $page->setSlug($slug);
        $page->setBlockTree(new StoredTree($page->getBlockTree()->layout, $blocks));

        if (null !== $channels) {
            self::channelsAndAudience()->setChannels($page, $channels);
        }

        if ($published) {
            $page->publish(new \DateTimeImmutable());
        }

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->persist($page);
        $manager->flush();

        return $page;
    }

    /**
     * Adds a translation to the page, created through the page translation resource's own factory, and flushes. On a
     * working copy, which Doctrine doesn't manage, the translation is only added. A locale the page has a translation
     * in already must go through that translation, since Sylius's getTranslation() falls back to another locale's
     */
    protected static function addTranslation(EntryInterface $page, string $localeCode, ?string $title, ?string $slug = null, bool $shown = true): PageTranslationInterface
    {
        /** @var FactoryInterface<PageTranslationInterface> $factory */
        $factory = self::getContainer()->get('setono_sylius_cms.factory.page_translation');

        $translation = $factory->createNew();
        $translation->setLocale($localeCode);
        $translation->setTitle($title);
        $translation->setSlug($slug);
        $translation->setShown($shown);
        $page->addTranslation($translation);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->flush();

        return $translation;
    }

    /**
     * Changes an entry's channels and audience as the plugin does, creating their rows through the resources' factories
     */
    protected static function channelsAndAudience(): ChannelsAndAudienceModifierInterface
    {
        $modifier = self::getContainer()->get(ChannelsAndAudienceModifierInterface::class);
        self::assertInstanceOf(ChannelsAndAudienceModifierInterface::class, $modifier);

        return $modifier;
    }

    /**
     * Makes the page the homepage of these channels as saving its settings does, which takes them off the pages that
     * were their homepages
     */
    protected static function setHomepageOf(PageInterface $page, string ...$channelCodes): void
    {
        $current = EntrySettings::of($page);

        /** @var EntrySettingsSaverInterface $saver */
        $saver = self::getContainer()->get(EntrySettingsSaverInterface::class);
        $saver->save($page, new EntrySettings($current->enabled, $current->dateWindow, $current->channels, $current->audience, $channelCodes));
    }

    /**
     * A heading block with its text in en_US
     */
    protected static function heading(string $key, string $text): StoredBlock
    {
        $block = new StoredBlock($key, 'heading', 1);
        $block->setValue('text', 'en_US', $text);

        return $block;
    }

    protected static function reloadPage(PageInterface $page): PageInterface
    {
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->clear();

        /** @var RepositoryInterface<PageInterface> $repository */
        $repository = self::getContainer()->get('setono_sylius_cms.repository.page');
        $reloaded = $repository->findOneBy(['uuid' => $page->getUuid()]);
        self::assertInstanceOf(PageInterface::class, $reloaded);

        return $reloaded;
    }

    /**
     * The page's draft as the database holds it, or null when it has none
     */
    protected static function draftOf(PageInterface $page): ?DraftInterface
    {
        /** @var DraftRepositoryInterface $drafts */
        $drafts = self::getContainer()->get('setono_sylius_cms.repository.draft');

        return $drafts->findOneByEntry(self::reloadPage($page));
    }

    /**
     * A preview token of the entry in a channel and a locale, as the builder gets one for its preview, shown to a
     * customer group value at a date, or to the shopper now
     */
    protected static function previewToken(EntryInterface|string $entry, string $channel = 'WEB', string $locale = 'en_US', ?string $shownTo = null, ?\DateTimeImmutable $date = null): string
    {
        /** @var PreviewTokenSigner $signer */
        $signer = self::getContainer()->get(PreviewTokenSigner::class);

        return $signer->sign(new Preview(is_string($entry) ? $entry : $entry->getUuid(), $channel, $locale, null, $shownTo, $date));
    }

    /**
     * Creates an enabled channel in en_US and USD with Sylius's example factory, named by its code unless a name is given
     */
    protected static function createChannel(string $code, ?string $name = null, ?string $hostname = null): void
    {
        /** @var ExampleFactoryInterface $factory */
        $factory = self::getContainer()->get('sylius.fixture.example_factory.channel');
        $channel = $factory->create([
            'code' => $code,
            'name' => $name ?? $code,
            'hostname' => $hostname ?? strtolower($code) . '.localhost',
            'enabled' => true,
            'locales' => ['en_US'],
            'default_locale' => 'en_US',
            'currencies' => ['USD'],
            'base_currency' => 'USD',
            'menu_taxon' => null,
            'default_tax_zone' => null,
        ]);
        self::assertInstanceOf(ChannelInterface::class, $channel);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('sylius.manager.channel');
        $manager->persist($channel);
        $manager->flush();
    }

    /**
     * Adds locales to a channel, after the ones it has, creating those the store doesn't have yet
     */
    protected static function addLocales(string $channelCode, string ...$localeCodes): void
    {
        /** @var RepositoryInterface<LocaleInterface> $locales */
        $locales = self::getContainer()->get('sylius.repository.locale');
        /** @var FactoryInterface<LocaleInterface> $localeFactory */
        $localeFactory = self::getContainer()->get('sylius.factory.locale');
        /** @var RepositoryInterface<ChannelInterface> $channels */
        $channels = self::getContainer()->get('sylius.repository.channel');
        $channel = $channels->findOneBy(['code' => $channelCode]);
        self::assertInstanceOf(ChannelInterface::class, $channel);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('sylius.manager.channel');
        foreach ($localeCodes as $localeCode) {
            $locale = $locales->findOneBy(['code' => $localeCode]);
            if (!$locale instanceof LocaleInterface) {
                $locale = $localeFactory->createNew();
                $locale->setCode($localeCode);
                $manager->persist($locale);
            }

            $channel->addLocale($locale);
        }

        $manager->flush();
    }

    /**
     * Autosaves changes onto the entry's working copy as the builder does, such as
     * ['op' => 'set_entry_value', 'field' => 'slug', 'locale' => 'en_US', 'value' => 'about-us']
     *
     * @param list<array<string, mixed>> $changes
     */
    protected static function saveChanges(EntryInterface $entry, array $changes): SaveResult
    {
        /** @var AutosaveInterface $autosave */
        $autosave = self::getContainer()->get(AutosaveInterface::class);
        /** @var SavedChangeSetRepositoryInterface $savedChangeSets */
        $savedChangeSets = self::getContainer()->get(SavedChangeSetRepositoryInterface::class);

        return $autosave->save($entry, ChangeSet::fromArray(['changes' => $changes]), $savedChangeSets->findLatestLockToken($entry));
    }

    /**
     * Publishes the entry's working copy as the builder's Publish does
     */
    protected static function publishEntry(EntryInterface $entry): void
    {
        /** @var EntryPublisherInterface $publisher */
        $publisher = self::getContainer()->get(EntryPublisherInterface::class);
        $publisher->publish($entry);
    }

    /**
     * The admin user "sylius" from the "test" fixtures suite
     */
    protected static function admin(): UserInterface
    {
        /** @var RepositoryInterface<AdminUserInterface> $repository */
        $repository = self::getContainer()->get('sylius.repository.admin_user');
        $admin = $repository->findOneBy(['username' => 'sylius']);
        self::assertInstanceOf(AdminUserInterface::class, $admin, 'The "test" fixtures suite must be loaded');
        self::assertInstanceOf(UserInterface::class, $admin);

        return $admin;
    }

    /**
     * The entry's problem count as it's stored
     */
    protected static function storedCount(EntryInterface $entry): ProblemCount
    {
        /** @var ProblemCountProviderInterface $provider */
        $provider = self::getContainer()->get(ProblemCountProviderInterface::class);

        return $provider->getCounts([$entry])[$entry->getUuid()];
    }

    protected static function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $manager */
        $manager = self::getContainer()->get('doctrine.orm.entity_manager');

        return $manager;
    }
}
