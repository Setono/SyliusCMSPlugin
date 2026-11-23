<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Symfony\Client\BrowserRegistry;

/**
 * playwright-symfony's browser, with its pages in a time zone, such as America/New_York, in place of the machine's:
 * BrowserRegistry opens its contexts with the "context" of its launch options, which takes Playwright's timezoneId.
 * PlaywrightTestCase replaces a browser that doesn't equal the one the environment asks for, which has no context
 * options, so this one equals any browser of the same type and headless mode. See UITestCase::useBrowserTimeZone()
 */
final class BrowserInTimeZone extends BrowserRegistry
{
    public function __construct(string $timeZone)
    {
        $environment = BrowserRegistry::fromEnvironment();

        parent::__construct($environment->getBrowserType(), $environment->isHeadless(), ['context' => ['timezoneId' => $timeZone]]);
    }

    public function equals(BrowserRegistry $other): bool
    {
        return $this->getBrowserType() === $other->getBrowserType() && $this->isHeadless() === $other->isHeadless();
    }
}
