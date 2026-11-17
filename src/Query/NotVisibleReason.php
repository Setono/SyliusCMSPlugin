<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Query;

/**
 * Why an entry isn't visible: the first rule of "When an entry shows" (docs/spec/content-model.md) it fails, in their
 * order. VisibleEntriesQueryInterface::whyNotVisible() answers it from the rules the visible queries filter on
 */
enum NotVisibleReason: string
{
    /** 1. It has never been published */
    case NotPublished = 'not_published';

    /** 2. It's disabled */
    case Disabled = 'disabled';

    /** 3. The time is outside its date window: before "Show from", or from "Show until" */
    case OutsideDateWindow = 'outside_date_window';

    /** 4. It isn't in the channel */
    case NotInChannel = 'not_in_channel';

    /** 5. It has no translation in the locale, or that translation is held back */
    case NotTranslated = 'not_translated';

    /** 6. Its audience leaves out the shopper's customer group */
    case OutsideAudience = 'outside_audience';
}
