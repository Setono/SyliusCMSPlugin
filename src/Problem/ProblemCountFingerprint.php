<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem;

use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ConditionKindMetadata;
use Setono\SyliusCMSPlugin\Registry\ConditionKindRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistryInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * A hash of the problem kinds whose problems are stored and of the registries they read: the block types, content
 * types, layouts and condition kinds, each with its fields, and so the field types. A deploy that adds or removes any
 * of them, or switches one off, changes it. The routes aren't in it: setono:cms:check-routes counts again the entries
 * it checks. It's read once a request
 */
final class ProblemCountFingerprint implements ProblemCountFingerprintInterface, ResetInterface
{
    /** @var non-empty-string|null */
    private ?string $fingerprint = null;

    /**
     * @param list<class-string> $kinds the classes of the kinds whose problems are stored, which RegisterProblemKindsPass hands it
     */
    public function __construct(
        private readonly array $kinds,
        private readonly BlockTypeRegistryInterface $blockTypes,
        private readonly ContentTypeRegistryInterface $contentTypes,
        private readonly LayoutRegistryInterface $layouts,
        private readonly ConditionKindRegistryInterface $conditionKinds,
    ) {
    }

    public function get(): string
    {
        return $this->fingerprint ??= hash('xxh128', serialize([
            $this->kinds,
            $this->blockTypes->all(),
            $contentTypes = $this->contentTypes->all(),
            array_map(fn (ContentTypeMetadata $contentType): array => $this->layouts->all($contentType->code), $contentTypes),
            // What a condition kind declares, without its service
            array_map(static fn (ConditionKindMetadata $kind): array => [$kind->code, $kind->needs, $kind->reads, $kind->params, $kind->fields], $this->conditionKinds->all()),
        ]));
    }

    public function reset(): void
    {
        $this->fingerprint = null;
    }
}
