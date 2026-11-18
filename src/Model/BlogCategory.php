<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\ContentType;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;

/**
 * The blog's categories, the category content type of the blog: a URL under "blog/category", any block at the top
 * level of the tree, which holds the category's post list, and the Standard layout, listed newest first. Their
 * translations' columns start with blog_category_, such as blog_category_description. See "Shipped content types" in
 * docs/spec/collections.md
 */
#[ContentType(
    code: self::CONTENT_TYPE,
    label: 'setono_sylius_cms.content_type.blog_category',
    prefix: 'blog/category',
    blocks: [new Allow('*')],
    descriptionField: 'description',
)]
class BlogCategory extends Entry implements BlogCategoryInterface
{
    public function __construct()
    {
        parent::__construct();

        // Blog category's shipped default layout (see Factory\EntryFactory)
        $this->setBlockTree(new StoredTree(new StoredLayout('standard', 1), []));
    }

    protected function createTranslation(): BlogCategoryTranslationInterface
    {
        return new BlogCategoryTranslation();
    }
}
