<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Upgrade;

use Setono\SyliusCMSPlugin\EntryFormat\EntryNormalizer;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\DraftRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\RevisionRepositoryInterface;
use Setono\SyliusCMSPlugin\Revision\RevisionReader;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;

/**
 * Writes an entry's stored data back upgraded, for setono:cms:upgrade: the block tree in its rows, its draft, which it
 * writes the way the builder's next save would, and its revisions. PresetRewriter rewrites editor presets.
 *
 * What was saved with a newer version than the code's is kept as it is. A draft or a revision saved with a newer version
 * of its content type is left as it is, because writing it back would drop the fields the code doesn't know
 *
 * @internal
 */
final readonly class EntryRewriter implements EntryRewriterInterface
{
    public function __construct(
        private StoredDataUpgraderInterface $upgrader,
        private ContentTypeRegistryInterface $contentTypes,
        private DraftRepositoryInterface $drafts,
        private WorkingCopyProviderInterface $workingCopies,
        private EntryNormalizer $entryNormalizer,
        private RevisionRepositoryInterface $revisions,
        private RevisionReader $revisionReader,
    ) {
    }

    /**
     * Upgrades the entry's rows, draft and revisions in place, without flushing
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
        $draftRewritten = null !== $draft && $this->rewriteDraft($entry, $draft, $newer);

        $revisions = 0;
        foreach ($this->revisions->findByEntry($entry) as $revision) {
            $upgraded = $this->revisionReader->read($revision);
            $newer = [...$newer, ...$upgraded->newer];
            if (self::hasNewerContentType($upgraded)) {
                continue;
            }

            $content = CanonicalJson::encode($this->entryNormalizer->normalize($this->revisionReader->entry($revision)));
            if ($content !== $revision->getContent()) {
                $revision->setContent($content);
                ++$revisions;
            }
        }

        return new EntryRewrite($rows !== $entry->getBlockTreeJson(), $draftRewritten, $newer, $revisions);
    }

    /**
     * @param list<NewerVersion> $newer what's newer than the code so far, which this adds the draft's to
     *
     * @return bool whether the draft was rewritten
     */
    private function rewriteDraft(EntryInterface $entry, DraftInterface $draft, array &$newer): bool
    {
        $content = $draft->getContent();
        $data = CanonicalJson::decode($content);
        $upgradedEntry = $this->upgrader->upgradeEntry(is_array($data) ? $data : []);
        $newer = [...$newer, ...$upgradedEntry->newer];

        if (self::hasNewerContentType($upgradedEntry)) {
            return false;
        }

        $draft->setContent(CanonicalJson::encode($this->entryNormalizer->normalize($this->workingCopies->getWorkingCopy($entry))));

        return $content !== $draft->getContent();
    }

    private static function hasNewerContentType(Upgraded $upgraded): bool
    {
        foreach ($upgraded->newer as $newerVersion) {
            if (Kind::ContentType === $newerVersion->kind) {
                return true;
            }
        }

        return false;
    }
}
