<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

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
}
