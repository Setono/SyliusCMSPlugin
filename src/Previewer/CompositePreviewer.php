<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Previewer;

use LogicException;
use Setono\SyliusCMSPlugin\Model\AssetInterface;
use SplPriorityQueue;

final class CompositePreviewer implements PreviewerInterface
{
    /**
     * @psalm-var SplPriorityQueue<array-key, PreviewerInterface>
     * @var SplPriorityQueue|PreviewerInterface[]
     */
    private readonly SplPriorityQueue $previewers;

    public function __construct()
    {
        /** @var SplPriorityQueue<array-key, PreviewerInterface> $this->previewers */
        $this->previewers = new SplPriorityQueue();
    }

    public function add(PreviewerInterface $previewer, int $priority = 0): void
    {
        $this->previewers->insert($previewer, $priority);
    }

    public function preview(AssetInterface $asset): Preview
    {
        foreach ($this->getPreviewers() as $previewer) {
            if ($previewer->supports($asset)) {
                return $previewer->preview($asset);
            }
        }

        throw new LogicException('No previewer available for given asset. This should not be possible since we have a catch all previewer');
    }

    public function supports(AssetInterface $asset): bool
    {
        foreach ($this->getPreviewers() as $previewer) {
            if ($previewer->supports($asset)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return SplPriorityQueue<array-key, PreviewerInterface>
     */
    private function getPreviewers(): SplPriorityQueue
    {
        return clone $this->previewers;
    }
}
