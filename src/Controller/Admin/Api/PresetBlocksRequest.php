<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Preset\SelectedBlocks;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The blocks ★ saves as a preset, which a request names as {"entry": <uuid>, "blocks": [<key>, …]}: the selected blocks
 * in the entry's working copy as the server has it, upgraded, so the builder sends its unsaved changes first
 *
 * @internal
 */
final readonly class PresetBlocksRequest
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private WorkingCopyProviderInterface $workingCopies,
        private StoredDataUpgraderInterface $upgrader,
    ) {
    }

    /**
     * @param array<array-key, mixed> $body
     *
     * @return non-empty-list<StoredBlock>
     *
     * @throws \InvalidArgumentException when the body names no entry or no blocks, or blocks the entry doesn't have
     * @throws NotFoundHttpException when there's no such entry
     */
    public function blocks(array $body): array
    {
        $entry = $body['entry'] ?? null;
        if (!is_string($entry) || '' === $entry) {
            throw new \InvalidArgumentException('The body needs the entry the blocks are in, as {"entry": <uuid>}');
        }

        $keys = [];
        foreach (is_array($body['blocks'] ?? null) ? $body['blocks'] : [null] as $key) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('The body needs the keys of the blocks to save, as {"blocks": [<key>, …]}');
            }

            $keys[] = $key;
        }

        $workingCopy = $this->workingCopies->getWorkingCopy($this->builderRequest->entry($entry));

        return SelectedBlocks::in($this->upgrader->readBlockTree($workingCopy)->blocks, $keys);
    }
}
