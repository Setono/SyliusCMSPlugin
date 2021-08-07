<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Extractor;

use Setono\SyliusCMSPlugin\Template\Metadata;
use Setono\SyliusCMSPlugin\Template\Template;
use Twig\Environment;

final class MetadataExtractor
{
    private Environment $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public function extract(Template $template): Metadata
    {
        $wrapper = $this->twig->load($template->getCode());
        $wrapper->getBlockNames();

        return new Metadata($wrapper->getBlockNames());
    }
}
