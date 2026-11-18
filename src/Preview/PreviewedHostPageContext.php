<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preview;

use Setono\SyliusCMSPlugin\Context\EntryContext;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContextFactoryInterface;

/**
 * The host-page context the previewed entry renders its own tree with outside a slot, such as in the blocks the builder
 * patches in and on a snippet's plain preview page: an entry with its own URL provides itself, and a snippet gets the
 * preview's sample object, as the slot of its placement provides it on the host page. See "Snippet preview" in
 * docs/spec/builder.md
 *
 * @internal
 */
final readonly class PreviewedHostPageContext
{
    public function __construct(
        private PreviewContext $previewContext,
        private SampleObjects $samples,
        private RenderContextFactoryInterface $renderContextFactory,
    ) {
    }

    /**
     * @return array<string, object>
     */
    public function of(EntryInterface $workingCopy): array
    {
        if (!$workingCopy instanceof SnippetInterface) {
            return EntryContext::objects($workingCopy);
        }

        $sample = $this->previewContext->getPreview()?->sample;
        if (null === $sample) {
            return [];
        }

        $object = $this->samples->load($sample, $this->renderContextFactory->create());

        return null === $object ? [] : [$sample->key => $object];
    }
}
