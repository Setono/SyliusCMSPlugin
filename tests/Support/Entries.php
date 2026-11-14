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
     * Creates a page in en_US through the page resource's own factory, with a heading block when $heading is given
     */
    protected static function createPage(string $title, string $slug, ?string $heading = null, bool $published = false): PageInterface
    {
        /** @var FactoryInterface<PageInterface> $factory */
        $factory = self::getContainer()->get('setono_sylius_cms.factory.page');

        $page = $factory->createNew();
        $page->setCurrentLocale('en_US');
        $page->setFallbackLocale('en_US');
        $page->setTitle($title);
        $page->setSlug($slug);

        if (null !== $heading) {
            $block = new StoredBlock('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', 'heading', 1);
            $block->setValue('text', 'en_US', $heading);
            $page->setBlockTree(new StoredTree($page->getBlockTree()->layout, [$block]));
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
