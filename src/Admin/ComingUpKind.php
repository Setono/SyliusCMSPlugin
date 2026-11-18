<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

/**
 * What happens to an entry in the Overview's Coming up, in the order the panel lists what happens at the same time.
 * See "Overview" in docs/spec/admin.md
 */
enum ComingUpKind: string
{
    /** Its scheduled publish runs */
    case Publishes = 'publishes';

    /** Its date window starts */
    case Starts = 'starts';

    /** Its date window ends */
    case Ends = 'ends';

    /**
     * The Semantic UI icon the panel shows it with, as the classes before "icon"
     */
    public function icon(): string
    {
        return match ($this) {
            self::Publishes => 'clock outline',
            self::Starts => 'eye',
            self::Ends => 'eye slash',
        };
    }
}
