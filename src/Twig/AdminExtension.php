<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The functions the CMS admin's own templates use, such as the field templates of the entry lists' grids and the
 * sections of the entry overview page and the panels of the Overview. They're internal to those templates, unlike the
 * cms_* functions of the spec. Their logic is in AdminRuntime, EntryOverviewRuntime and OverviewRuntime, which Twig
 * builds only when a template calls one
 *
 * @internal
 */
final class AdminExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('cms_admin_entry_row', [AdminRuntime::class, 'entryRow']),
            new TwigFunction('cms_admin_translation_chips', [AdminRuntime::class, 'translationChips']),
            new TwigFunction('cms_admin_entry_urls', [EntryOverviewRuntime::class, 'entryUrls']),
            new TwigFunction('cms_admin_channel_homepages', [EntryOverviewRuntime::class, 'channelHomepages']),
            new TwigFunction('cms_admin_entry_problems', [EntryOverviewRuntime::class, 'entryProblems']),
            new TwigFunction('cms_admin_entry_revisions', [EntryOverviewRuntime::class, 'entryRevisions']),
            new TwigFunction('cms_admin_entry_share_links', [EntryOverviewRuntime::class, 'entryShareLinks']),
            new TwigFunction('cms_admin_where_it_shows', [EntryOverviewRuntime::class, 'whereItShows']),
            new TwigFunction('cms_admin_entry_placements', [EntryOverviewRuntime::class, 'entryPlacements']),
            new TwigFunction('cms_admin_entry_used_in', [EntryOverviewRuntime::class, 'entryUsedIn']),
            new TwigFunction('cms_admin_page_scope_editor', [PageScopeEditorRuntime::class, 'config']),
            new TwigFunction('cms_admin_share_link_targets', [EntryOverviewRuntime::class, 'shareLinkTargets']),
            new TwigFunction('cms_admin_content_type', [AdminRuntime::class, 'contentType']),
            new TwigFunction('cms_admin_content_types', [AdminRuntime::class, 'contentTypes']),
            new TwigFunction('cms_admin_customer_group_names', [AdminRuntime::class, 'customerGroupNames']),
            new TwigFunction('cms_admin_needs_attention', [OverviewRuntime::class, 'needsAttention']),
            new TwigFunction('cms_admin_missing_alt_text', [OverviewRuntime::class, 'missingAltText']),
            new TwigFunction('cms_admin_preset_problems', [OverviewRuntime::class, 'presetProblems']),
            new TwigFunction('cms_admin_coming_up', [OverviewRuntime::class, 'comingUp']),
            new TwigFunction('cms_admin_translation_completeness', [OverviewRuntime::class, 'translationCompleteness']),
            new TwigFunction('cms_admin_drafts_in_progress', [OverviewRuntime::class, 'draftsInProgress']),
        ];
    }
}
