<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The blocks a Snippet block shows, which "Detach to edit here" copies in its place: the snippet's published content,
 * which is what it shows, upgraded, in the stored block tree format. Blocks of a type the code no longer has don't
 * show, so they're left out. The builder gives the copies new keys. See "Snippet references" in docs/spec/builder.md
 */
final readonly class SnippetBlocksAction
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private StoredDataUpgraderInterface $upgrader,
        private BlockTypeRegistryInterface $blockTypes,
    ) {
    }

    public function __invoke(string $uuid): JsonResponse
    {
        $snippet = $this->builderRequest->entry($uuid);
        if (!$snippet instanceof SnippetInterface) {
            throw new NotFoundHttpException(sprintf('The entry %s isn\'t a snippet', $uuid));
        }

        $serializer = new StoredTreeSerializer();

        return new JsonResponse(CanonicalJson::encode(new ListedObject(['blocks' => array_map(
            $serializer->normalizeBlock(...),
            $this->known($this->upgrader->readBlockTree($snippet)->blocks),
        )])), json: true);
    }

    /**
     * The blocks of the types the code has, each with only its children of those
     *
     * @param list<StoredBlock> $blocks
     *
     * @return list<StoredBlock>
     */
    private function known(array $blocks): array
    {
        $known = [];
        foreach ($blocks as $block) {
            if ($this->blockTypes->has($block->type)) {
                $block->children = $this->known($block->children);
                $known[] = $block;
            }
        }

        return $known;
    }
}
