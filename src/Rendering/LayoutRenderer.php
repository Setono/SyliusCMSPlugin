<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistryInterface;
use Setono\SyliusCMSPlugin\Tree\ReferenceFinder;
use Setono\SyliusCMSPlugin\Twig\CMSRuntime;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Twig\Environment;

final readonly class LayoutRenderer implements LayoutRendererInterface
{
    public function __construct(
        private ContentTypeRegistryInterface $contentTypes,
        private LayoutRegistryInterface $layouts,
        private StoredDataUpgraderInterface $upgrader,
        private BlockViewBuilder $blockViewBuilder,
        private BlockHydrator $hydrator,
        private Environment $twig,
        private ReferenceFinder $referenceFinder,
        private ReferenceLoaderInterface $referenceLoader,
    ) {
    }

    public function render(EntryInterface $entry, RenderContext $context): string
    {
        $contentType = $this->contentTypes->getByEntry($entry)->code;
        $tree = $this->upgrader->readBlockTree($entry);
        $stored = $tree->layout;

        $layout = $this->layouts->resolve($contentType, $stored?->code)
            ?? throw new \LogicException(sprintf('The content type "%s" has no layouts, so its entries have no page of their own to render', $contentType));

        // The default layout of an entry whose layout was removed or disabled ignores the values stored for that one
        $values = null !== $stored && $stored->code === $layout->code ? $stored->fields : [];

        // One pass loads the references of the layout and of the blocks together, with one query per type
        $references = $this->referenceLoader->load(ReferenceFinder::references((function () use ($layout, $values, $tree): \Generator {
            yield from $this->referenceFinder->inValues($layout->fields, $values);
            yield from $this->referenceFinder->inBlocks($tree->blocks);
        })()), $context);

        return $this->twig->render($layout->template, [
            'entry' => $entry,
            'layout' => $this->hydrator->hydrateObject($layout->class, $layout->fields, $values, $context, $references),
            'blocks' => $this->blockViewBuilder->build($tree->blocks, $context, $references),
            'context' => $context->getHostPageContext(),
            CMSRuntime::RENDER_CONTEXT => $context,
        ]);
    }
}
