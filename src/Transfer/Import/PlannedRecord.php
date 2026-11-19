<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

/**
 * A record of the export and what the import does with it, as the plan lists it
 */
final readonly class PlannedRecord
{
    /**
     * @param string $label what names it: an entry's title, or a preset's name
     * @param bool $picked whether the export picked it, rather than it coming along as a dependency
     * @param string|null $contentType an entry's content type's code
     */
    public function __construct(
        public RecordKind $kind,
        public string $uuid,
        public string $label,
        public bool $picked,
        public ImportAction $action,
        public ?string $contentType = null,
    ) {
    }

    /**
     * Whether the import writes it: creates it, or updates it
     */
    public function writes(): bool
    {
        return $this->action->writes();
    }
}
