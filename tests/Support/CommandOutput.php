<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Symfony\Component\Console\Tester\CommandTester;

/**
 * A console command's output as the transfer commands' tests read it
 */
trait CommandOutput
{
    /**
     * The command's output on one line, as SymfonyStyle wraps it
     */
    private static function display(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }

    /**
     * The command's output without any whitespace, for messages with a path SymfonyStyle may wrap anywhere
     */
    private static function squashed(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', '', $tester->getDisplay());
    }
}
