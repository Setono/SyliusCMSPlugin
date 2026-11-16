<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig;

use Setono\SyliusCMSPlugin\Admin\ChannelHomepages;
use Setono\SyliusCMSPlugin\Admin\EntryRevisions;
use Setono\SyliusCMSPlugin\Admin\EntryRows;
use Setono\SyliusCMSPlugin\Admin\EntryShareLinks;
use Setono\SyliusCMSPlugin\Admin\EntryUrls;
use Setono\SyliusCMSPlugin\Admin\TranslationChips;
use Setono\SyliusCMSPlugin\Admin\WhereItShows;
use Setono\SyliusCMSPlugin\Problem\ProblemFinder;
use Setono\SyliusCMSPlugin\Problem\ProblemSummaryProvider;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The functions the CMS admin's own templates use, such as the field templates of the entry lists' grids and the
 * sections of the entry overview page. They're internal to those templates, unlike the cms_* functions of the spec
 *
 * @internal
 */
final class AdminExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('cms_admin_entry_row', [EntryRows::class, 'get']),
            new TwigFunction('cms_admin_entry_urls', [EntryUrls::class, 'of']),
            new TwigFunction('cms_admin_channel_homepages', [ChannelHomepages::class, 'of']),
            new TwigFunction('cms_admin_translation_chips', [TranslationChips::class, 'of']),
            new TwigFunction('cms_admin_entry_problems', [ProblemFinder::class, 'findByEntry']),
            new TwigFunction('cms_admin_problem_summary', [ProblemSummaryProvider::class, 'getSummary']),
            new TwigFunction('cms_admin_entry_revisions', [EntryRevisions::class, 'of']),
            new TwigFunction('cms_admin_entry_share_links', [EntryShareLinks::class, 'of']),
            new TwigFunction('cms_admin_where_it_shows', [WhereItShows::class, 'of']),
            new TwigFunction('cms_admin_share_link_targets', [EntryShareLinks::class, 'targets']),
            new TwigFunction('cms_admin_content_type', [AdminRuntime::class, 'contentType']),
            new TwigFunction('cms_admin_content_types', [AdminRuntime::class, 'contentTypes']),
            new TwigFunction('cms_admin_customer_group_names', [AdminRuntime::class, 'customerGroupNames']),
        ];
    }
}
