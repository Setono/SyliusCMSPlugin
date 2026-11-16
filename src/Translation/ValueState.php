<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Translation;

/**
 * The state of a translatable value in a locale. See "Translation state" in docs/spec/content-model.md
 */
enum ValueState: string
{
    /** Missing or empty in the locale while another locale has a value */
    case Untranslated = 'untranslated';

    /** The value it was translated from has changed since */
    case Outdated = 'outdated';

    /** Translated, and its source hasn't changed since, or typed without a source */
    case UpToDate = 'up_to_date';
}
