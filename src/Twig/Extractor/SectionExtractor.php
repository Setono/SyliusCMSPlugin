<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\Extractor;

use Twig\Environment;
use Twig\Template;
use Twig\TemplateWrapper;
use Webmozart\Assert\Assert;

final class SectionExtractor implements SectionExtractorInterface
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function extract($template): array
    {
        if (is_string($template)) {
            $template = $this->twig->load($template);
        }

        if (!$template instanceof Template && !$template instanceof TemplateWrapper) {
            throw new \InvalidArgumentException(sprintf(
                'The given template must be either a %s, %s, or a string representing an existing template',
                Template::class,
                TemplateWrapper::class,
            ));
        }

        $sections = [];

        /** @psalm-suppress InternalMethod */
        foreach ($template->getBlockNames([]) as $blockName) {
            Assert::string($blockName);

            $pos = stripos($blockName, self::SECTION_PREFIX);
            if (0 === $pos) {
                $sections[] = substr($blockName, strlen(self::SECTION_PREFIX));
            }
        }

        return $sections;
    }
}
