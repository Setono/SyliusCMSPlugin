<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Generator\Page;

use Setono\SyliusCMSPlugin\Model\PageInterface;

interface PreviewLinkGeneratorInterface
{
    /**
     * @return iterable|PreviewLink[]
     */
    public function generateAll(PageInterface $page): iterable;
}
