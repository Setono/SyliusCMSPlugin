<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * A kind of problem, such as a block of a type the code no longer has. A kind is a service tagged
 * setono_sylius_cms.problem_kind, which autoconfiguration adds, so adding one changes nothing else: the overview page,
 * the lists' marker, the Needs attention filter and the Content badge all read every kind. setono:cms:lint reports a
 * kind too when its tag has `lint: true`. See "Problems" in docs/spec/admin.md
 */
interface ProblemKindInterface
{
    /**
     * The problems of this kind in the entry
     *
     * @param EntryInterface $entry the entry as it's checked, with its block tree upgraded: in the admin, its working
     *                              copy; in setono:cms:lint, its published content and its draft, one at a time
     *
     * @return iterable<Problem>
     */
    public function findIn(EntryInterface $entry): iterable;
}
