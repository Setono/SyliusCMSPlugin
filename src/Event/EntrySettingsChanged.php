<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Event;

/**
 * Dispatched after the transaction that saved an entry's on-save settings commits. They take effect at once, outside
 * the draft. See "What takes effect on save" in docs/spec/publishing.md
 */
final readonly class EntrySettingsChanged implements StorefrontChangedInterface
{
    public const ENABLED = 'enabled';

    public const DATE_WINDOW = 'date_window';

    public const CHANNELS = 'channels';

    public const AUDIENCE = 'audience';

    public const HOMEPAGE = 'homepage';

    public const POSITION = 'position';

    /**
     * @param list<self::ENABLED|self::DATE_WINDOW|self::CHANNELS|self::AUDIENCE|self::HOMEPAGE|self::POSITION> $changed what changed, in this order
     */
    public function __construct(
        public string $uuid,
        public array $changed,
    ) {
    }

    public function getAffectedUuids(): array
    {
        return [$this->uuid];
    }
}
