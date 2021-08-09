<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Template;

interface MetadataExtractorInterface
{
    /**
     * Extracts and returns metadata for the given template
     */
    public function extract(Template $template): Metadata;
}
