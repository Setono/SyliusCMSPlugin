<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Sylius\Component\Core\Model\AdminUserInterface;
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
            $page->setChannelCodes($channels);
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
}
