<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Setono\SyliusCMSPlugin\Tests\Application\Kernel;

/**
 * The test application plus what a test adds to it in build(), such as a block type or some config. Each has a cache of
 * its own, so what it adds stays out of every other test, and it's built once and reused
 */
abstract class FixtureKernel extends Kernel
{
    public function getProjectDir(): string
    {
        return dirname(__DIR__) . '/Application';
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir() . '_' . $this->cacheSuffix();
    }

    /**
     * What the name of its cache directory ends with
     */
    abstract protected function cacheSuffix(): string;
}
