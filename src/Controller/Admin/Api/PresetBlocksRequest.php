<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Preset\InvalidPresetRequestException;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\SelectedBlocks;
use Setono\SyliusCMSPlugin\Preset\StartingPoint;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * What ★ or the entry settings save as a preset, which a request names as {"entry": <uuid>, "blocks": [<key>, …]}, the
 * selected blocks, or {"entry": <uuid>, "startingPoint": true}, the whole tree, as a starting point for the entry's
 * content type with its layout and the layout's field values. Both are read from the entry's working copy as the
 * server has it, upgraded, so the builder sends its unsaved changes first
 *
 * @internal
 */
final readonly class PresetBlocksRequest
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private WorkingCopyProviderInterface $workingCopies,
        private StoredDataUpgraderInterface $upgrader,
        private ContentTypeRegistryInterface $contentTypes,
    ) {
    }

    /**
     * The blocks to save, and the starting point when the request saves the whole tree, as a preset without a name
     *
     * @param array<array-key, mixed> $body
     *
     * @throws \InvalidArgumentException when the body names no entry or no blocks, or blocks the entry doesn't have, or
     *                                   asks for the whole tree of an entry without blocks, or of a content type
     *                                   without a block tree
     * @throws NotFoundHttpException when there's no such entry
     */
    public function content(array $body): Preset
    {
        if (true === ($body['startingPoint'] ?? null)) {
            return $this->wholeTree($body);
        }

        return new Preset('', $this->blocks($body));
    }

    /**
     * @param array<array-key, mixed> $body
     *
     * @return non-empty-list<StoredBlock>
     */
    private function blocks(array $body): array
    {
        $keys = [];
        foreach (is_array($body['blocks'] ?? null) ? $body['blocks'] : [null] as $key) {
            if (!is_string($key)) {
                // {"blocks": [<key>, …]}, or {"startingPoint": true}
                throw new InvalidPresetRequestException(new TranslatableMessage('setono_sylius_cms.preset.request.blocks'));
            }

            $keys[] = $key;
        }

        $workingCopy = $this->workingCopies->getWorkingCopy($this->builderRequest->entry(self::entry($body)));

        return SelectedBlocks::in($this->upgrader->readBlockTree($workingCopy)->blocks, $keys);
    }

    /**
     * @param array<array-key, mixed> $body
     */
    private function wholeTree(array $body): Preset
    {
        $workingCopy = $this->workingCopies->getWorkingCopy($this->builderRequest->entry(self::entry($body)));
        $contentType = $this->contentTypes->getByEntry($workingCopy);
        if (null === $contentType->blocks) {
            throw new InvalidPresetRequestException(new TranslatableMessage('setono_sylius_cms.preset.request.no_block_tree', ['%content_type%' => $contentType->code]));
        }

        $tree = $this->upgrader->readBlockTree($workingCopy);
        if ([] === $tree->blocks) {
            throw new InvalidPresetRequestException(new TranslatableMessage('setono_sylius_cms.preset.request.no_blocks'));
        }

        return new Preset('', $tree->blocks, startingPoint: new StartingPoint($contentType->code, $tree->layout));
    }

    /**
     * @param array<array-key, mixed> $body
     */
    private static function entry(array $body): string
    {
        $entry = $body['entry'] ?? null;
        if (!is_string($entry) || '' === $entry) {
            // {"entry": <uuid>}
            throw new InvalidPresetRequestException(new TranslatableMessage('setono_sylius_cms.preset.request.entry'));
        }

        return $entry;
    }
}
