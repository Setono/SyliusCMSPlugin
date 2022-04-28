<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\Extractor;

use Twig\Template;
use Twig\TemplateWrapper;

interface SectionExtractorInterface
{
    /**
     * This is the prefix you need on block names for it to be considered a 'section'
     */
    public const SECTION_PREFIX = 'sscms_section_';

    /**
     * Returns the sscms_section's defined in the given twig source
     *
     * @param Template|TemplateWrapper|string $template
     *
     * @return list<string>
     */
    public function extract($template): array;
}
