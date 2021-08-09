<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Template;

use Setono\SyliusCMSPlugin\Twig\TokenParser\SectionNode;
use Twig\Environment;
use Twig\Node\Node;

final class MetadataExtractor implements MetadataExtractorInterface
{
    private Environment $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public function extract(Template $template): Metadata
    {
        /**
         * The method \Twig\Template::getSourceContext() is marked internal in newer versions of twig,
         * but _also_ in newer versions of Twig, the \Twig\Template class isn't returned from the \Twig\Environment::resolveTemplate method,
         * but instead the \Twig\TemplateWrapper is returned and here the getSourceContext() isn't marked as internal
         *
         * @psalm-suppress InternalMethod
         */
        $source = $this->twig->resolveTemplate($template->getCode())->getSourceContext();
        $tokenStream = $this->twig->tokenize($source);
        $moduleNode = $this->twig->parse($tokenStream);

        $sections = [];

        /** @var array<array-key, Node> $nodes */
        $nodes = [$moduleNode];

        while (count($nodes) > 0) {
            $nextNode = array_pop($nodes);

            /** @var Node $node */
            foreach ($nextNode as $node) {
                $nodes[] = $node;
            }

            if ($nextNode instanceof SectionNode) {
                $sections[] = (string) $nextNode->getAttribute('name');
            }
        }

        return new Metadata($sections);
    }
}
