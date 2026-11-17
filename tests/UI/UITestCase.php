<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Playwright\Locator\LocatorInterface;
use Playwright\Symfony\Test\PlaywrightTestCase;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;

/**
 * Every UI test extends this one test case, which drives a real browser through the test application with
 * playwright-php/playwright-symfony. The kernel runs in the test's PHP process, inside the test's database transaction,
 * so data a test creates is visible to the pages it visits
 */
abstract class UITestCase extends PlaywrightTestCase
{
    use Entries;

    protected function signInAsAdmin(): void
    {
        $this->loginUser(self::admin(), 'admin');
    }

    /**
     * That the locator finds as many elements as there are texts, each with its text, or containing it. playwright-php's
     * toHaveText() doesn't take a list, so each element is checked on its own
     *
     * @param list<string> $texts
     */
    protected static function assertTexts(array $texts, LocatorInterface $locator, bool $contains = false): void
    {
        Expect::locator($locator)->toHaveCount(count($texts));
        foreach ($texts as $index => $text) {
            if ($contains) {
                Expect::locator($locator->nth($index))->toContainText($text);
            } else {
                Expect::locator($locator->nth($index))->toHaveText($text);
            }
        }
    }
}
