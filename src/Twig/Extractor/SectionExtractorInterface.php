<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\Extractor;

interface SectionExtractorInterface
{
    /**
     * Returns the sscms_section's defined in the given twig source
     *
     * @param string $source the twig source string
     *
     * @return list<string>
     */
    public function extract(string $source): array;
}
