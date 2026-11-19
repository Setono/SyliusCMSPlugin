<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * One or more saved blocks, with everything in them, that are copied when inserted: a code preset, shipped as a file, or
 * an editor preset, saved in the builder. Both are in the preset file format, which PresetSerializer reads and writes.
 * See "Presets" and "Preset file format" in docs/spec/layouts-and-presets.md
 */
final readonly class Preset
{
    /** The version of the preset file format. A change to the format itself bumps it, and is a breaking change */
    public const FORMAT = 1;

    /**
     * @param list<StoredBlock> $blocks the top-level blocks, in order, each with its children, in the stored block format
     * @param StartingPoint|null $startingPoint null for a block preset
     * @param string|null $code a code preset's code, its file name without .json, which isn't in the file
     * @param string|null $thumbnail a code preset's thumbnail: a public path to an image, or null
     * @param string|null $uuid an editor preset's UUID, which a code preset doesn't have
     * @param \DateTimeImmutable|null $changedAt when an editor preset last changed
     */
    public function __construct(
        public string|TranslationKey $name,
        public array $blocks,
        public string|TranslationKey|null $category = null,
        public string|TranslationKey|null $description = null,
        public ?StartingPoint $startingPoint = null,
        public ?string $code = null,
        public ?string $thumbnail = null,
        public ?string $uuid = null,
        public ?\DateTimeImmutable $changedAt = null,
    ) {
    }

    /**
     * Whether it's a code preset, which is read-only in the admin, rather than an editor preset, which has a UUID
     */
    public function isFromCode(): bool
    {
        return null === $this->uuid;
    }

    /**
     * What names it in the builder's endpoints: an editor preset's UUID, or a code preset's code. A code matches
     * ^[a-z][a-z0-9_]*$ and a UUID has hyphens, so the two never collide
     */
    public function id(): string
    {
        return $this->uuid ?? $this->code ?? throw new \LogicException('A code preset needs its code, which is its file name');
    }
}
