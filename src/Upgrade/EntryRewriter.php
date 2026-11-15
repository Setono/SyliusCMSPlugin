<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Upgrade;

use Setono\SyliusCMSPlugin\EntryFormat\EntryNormalizer;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\DraftRepositoryInterface;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;

/**
 * Writes an entry's stored data back upgraded, for setono:cms:upgrade: the block tree in its rows, and its draft,
 * which it writes the way the builder's next save would. Revisions (#240) and editor presets (#270) join with their
 * tickets.
 *
 * What was saved with a newer version than the code's is kept as it is. A draft saved with a newer version of its
 * content type is left as it is, because writing it back would drop the fields the code doesn't know
 *
 * @internal
 */
final readonly class EntryRewriter
{
    public function __construct(
        private StoredDataUpgraderInterface $upgrader,
        private ContentTypeRegistryInterface $contentTypes,
        private DraftRepositoryInterface $drafts,
        private WorkingCopyProviderInterface $workingCopies,
        private EntryNormalizer $entryNormalizer,
    ) {
    }

    /**
     * Upgrades the entry's rows and draft in place, without flushing
     */
    public function rewrite(EntryInterface $entry): EntryRewrite
    {
        $serializer = new StoredTreeSerializer();

        $rows = $entry->getBlockTreeJson();
        $tree = CanonicalJson::decode($rows);
        $upgradedTree = $this->upgrader->upgradeTree(is_array($tree) ? $tree : [], $this->contentTypes->getByEntry($entry)->code);
        $entry->setBlockTree($serializer->denormalize($upgradedTree->data));
        $newer = $upgradedTree->newer;

        $draft = $this->drafts->findOneByEntry($entry);
        if (null === $draft) {
            return new EntryRewrite($rows !== $entry->getBlockTreeJson(), false, $newer);
        }

        $content = $draft->getContent();
        $data = CanonicalJson::decode($content);
        $upgradedEntry = $this->upgrader->upgradeEntry(is_array($data) ? $data : []);
        $newer = [...$newer, ...$upgradedEntry->newer];

        foreach ($upgradedEntry->newer as $newerVersion) {
            if (Kind::ContentType === $newerVersion->kind) {
                return new EntryRewrite($rows !== $entry->getBlockTreeJson(), false, $newer);
            }
        }

        $draft->setContent(CanonicalJson::encode($this->entryNormalizer->normalize($this->workingCopies->getWorkingCopy($entry))));

        return new EntryRewrite($rows !== $entry->getBlockTreeJson(), $content !== $draft->getContent(), $newer);
    }
}
