<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\ContentType;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;

/**
 * The built-in content type for standalone pages with their own URL and a block tree
 */
#[ContentType(
    code: 'page',
    label: 'setono_sylius_cms.content_type.page',
    prefix: '',
    blocks: [new Allow('*')],
)]
class Page extends Entry implements PageInterface
{
    public function __construct()
    {
        parent::__construct();

        // Page's shipped default layout. Created through its resource factory, a page starts in the default layout the
        // config resolves, which a project can change (see Factory\EntryFactory)
        $this->setBlockTree(new StoredTree(new StoredLayout('standard', 1), []));
    }

    protected function createTranslation(): PageTranslationInterface
    {
        return new PageTranslation();
    }
}
