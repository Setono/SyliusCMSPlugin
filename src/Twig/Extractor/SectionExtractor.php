<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\Extractor;

use Setono\SyliusCMSPlugin\Twig\TokenParser\SectionNode;
use Twig\Environment;
use Twig\Node\Node;

final class SectionExtractor implements SectionExtractorInterface
{
    private Environment $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public function extract(string $source): array
    {
        $templateWrapper = $this->twig->createTemplate($source);
        $tokenStream = $this->twig->tokenize($templateWrapper->getSourceContext());
        $moduleNode = $this->twig->parse($tokenStream);

        $sections = [];

        /** @var array<array-key, Node> $nodes */
        $nodes = [$moduleNode];

        while (count($nodes) > 0) {
            $nextNode = array_shift($nodes);

            /** @var Node $node */
            foreach ($nextNode as $node) {
                $nodes[] = $node;
            }

            if ($nextNode instanceof SectionNode) {
                $sections[] = (string) $nextNode->getAttribute('name');
            }
        }

        return $sections;
    }
}
