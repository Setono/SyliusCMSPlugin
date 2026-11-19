<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

/**
 * What stops an import until someone overwrites the records or fixes the clash. See "Conflicts" in
 * docs/spec/transfer.md
 */
enum ConflictKind: string
{
    /** A picked entry has a draft in the target, which overwriting discards */
    case Draft = 'draft';

    /** A picked entry has a scheduled publish in the target, which overwriting cancels */
    case ScheduledPublish = 'scheduled_publish';

    /** A picked record changed in the target after the export's copy */
    case Stale = 'stale';

    /** An entry's URL clashes with another entry's in a channel and locale, which an editor fixes by changing a slug */
    case UrlClash = 'url_clash';

    /**
     * Whether --overwrite, or the admin's checkbox, resolves it. It never resolves a URL clash
     */
    public function isResolvedByOverwrite(): bool
    {
        return self::UrlClash !== $this;
    }
}
