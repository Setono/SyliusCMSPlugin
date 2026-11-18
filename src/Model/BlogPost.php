<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\ContentType;
use Setono\SyliusCMSPlugin\Field\DateTime;
use Setono\SyliusCMSPlugin\Field\Entries;
use Setono\SyliusCMSPlugin\Field\Media;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;

/**
 * The blog's posts: a URL under "blog", any block at the top level of the tree, the Article layout, listed newest first
 * by their date, in the blog's categories. Their columns start with blog_post_, such as blog_post_published_at.
 * See "Shipped content types" in docs/spec/collections.md
 */
#[ContentType(
    code: self::CONTENT_TYPE,
    label: 'setono_sylius_cms.content_type.blog_post',
    prefix: 'blog',
    blocks: [new Allow('*')],
    order: ['publishedAt' => 'DESC'],
    categoryType: BlogCategory::class,
    descriptionField: 'excerpt',
)]
class BlogPost extends Entry implements BlogPostInterface
{
    #[DateTime(label: 'setono_sylius_cms.field.blog_post.published_at')]
    protected ?\DateTimeImmutable $publishedAt = null;

    #[Media(label: 'setono_sylius_cms.field.blog_post.featured_image')]
    protected ?string $featuredImage = null;

    /** @var list<string>|null */
    #[Entries(label: 'setono_sylius_cms.field.blog_post.categories', contentType: BlogCategory::class, indexed: true, sources: false)]
    protected ?array $categories = null;

    public function __construct()
    {
        parent::__construct();

        // Blog post's shipped default layout. Created through its resource factory, a post starts in the default layout
        // the config resolves, which a project can change (see Factory\EntryFactory)
        $this->setBlockTree(new StoredTree(new StoredLayout('article', 1), []));
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return UtcColumn::read($this->publishedAt);
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): void
    {
        $this->publishedAt = $publishedAt?->setTimezone(new \DateTimeZone('UTC'));
    }

    /**
     * The first publish fills the date when it's empty, and a later one keeps it
     */
    public function fillOnPublish(\DateTimeImmutable $publishedAt): void
    {
        if (null === $this->publishedAt) {
            $this->setPublishedAt($publishedAt);
        }
    }

    public function getFeaturedImage(): ?string
    {
        return $this->featuredImage;
    }

    public function setFeaturedImage(?string $featuredImage): void
    {
        $this->featuredImage = $featuredImage;
    }

    public function getCategories(): array
    {
        return array_values(array_filter($this->categories ?? [], is_string(...)));
    }

    public function setCategories(array $categories): void
    {
        $this->categories = [] === $categories ? null : array_values($categories);
    }

    protected function createTranslation(): BlogPostTranslationInterface
    {
        return new BlogPostTranslation();
    }
}
