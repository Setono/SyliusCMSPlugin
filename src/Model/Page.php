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

        // Page's default layout. The layout machinery (#236) will pick the content type's default layout instead
        $this->setBlockTree(new StoredTree(new StoredLayout('standard', 1), []));
    }

    protected function createTranslation(): PageTranslationInterface
    {
        return new PageTranslation();
    }
}
