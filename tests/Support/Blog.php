<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\BlogCategoryInterface;
use Setono\SyliusCMSPlugin\Model\BlogCategoryTranslationInterface;
use Setono\SyliusCMSPlugin\Model\BlogPostInterface;
use Setono\SyliusCMSPlugin\Model\BlogPostTranslationInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryReferenceInterface;
use Setono\SyliusCMSPlugin\Publishing\EntryPublisherInterface;
use Setono\SyliusCMSPlugin\Repository\EntryReferenceRepositoryInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Helpers for the blog: categories and posts, created in en_US through their resources' factories, so like any new
 * entry they're in every channel and their content type's default layout. A published one is published through the
 * publisher, as the builder's Publish does, so its date is filled and its categories are written to the reference table
 */
trait Blog
{
    abstract protected static function getContainer(): ContainerInterface;

    protected static function createBlogCategory(string $title, string $slug, ?string $description = null, bool $published = false): BlogCategoryInterface
    {
        /** @var FactoryInterface<BlogCategoryInterface> $factory */
        $factory = self::getContainer()->get('setono_sylius_cms.factory.blog_category');

        $category = $factory->createNew();
        $category->setCurrentLocale('en_US');
        $category->setFallbackLocale('en_US');
        $category->setTitle($title);
        $category->setSlug($slug);

        $translation = $category->getTranslation('en_US');
        self::assertInstanceOf(BlogCategoryTranslationInterface::class, $translation);
        $translation->setDescription($description);

        return self::saveBlogEntry($category, $published);
    }

    /**
     * @param list<BlogCategoryInterface|string> $categories the categories, or their UUIDs, in the editor's order
     * @param list<StoredBlock> $blocks the top-level blocks of its tree, in its default layout
     */
    protected static function createBlogPost(
        string $title,
        string $slug,
        array $categories = [],
        bool $published = false,
        ?\DateTimeImmutable $publishedAt = null,
        ?string $excerpt = null,
        ?string $featuredImage = null,
        array $blocks = [],
    ): BlogPostInterface {
        /** @var FactoryInterface<BlogPostInterface> $factory */
        $factory = self::getContainer()->get('setono_sylius_cms.factory.blog_post');

        $post = $factory->createNew();
        $post->setCurrentLocale('en_US');
        $post->setFallbackLocale('en_US');
        $post->setTitle($title);
        $post->setSlug($slug);
        $post->setCategories(array_map(static fn (BlogCategoryInterface|string $category): string => is_string($category) ? $category : $category->getUuid(), $categories));
        $post->setPublishedAt($publishedAt);
        $post->setFeaturedImage($featuredImage);
        $post->setBlockTree(new StoredTree($post->getBlockTree()->layout, $blocks));

        $translation = $post->getTranslation('en_US');
        self::assertInstanceOf(BlogPostTranslationInterface::class, $translation);
        $translation->setExcerpt($excerpt);

        return self::saveBlogEntry($post, $published);
    }

    /**
     * What the reference table holds for the entry: its field and UUID per row, sorted
     *
     * @return list<array{string, string}>
     */
    protected static function referencesOf(EntryInterface $entry): array
    {
        /** @var EntryReferenceRepositoryInterface $repository */
        $repository = self::getContainer()->get(EntryReferenceRepositoryInterface::class);

        $references = array_map(static fn (EntryReferenceInterface $reference): array => [$reference->getField(), $reference->getUuid()], $repository->findByEntry($entry));
        sort($references);

        return $references;
    }

    /**
     * @template T of EntryInterface
     *
     * @param T $entry
     *
     * @return T
     */
    private static function saveBlogEntry(EntryInterface $entry, bool $published): EntryInterface
    {
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.entry');
        $manager->persist($entry);
        $manager->flush();

        if ($published) {
            /** @var EntryPublisherInterface $publisher */
            $publisher = self::getContainer()->get(EntryPublisherInterface::class);
            $publisher->publish($entry);
        }

        return $entry;
    }
}
