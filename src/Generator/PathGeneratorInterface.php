<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Generator;

use Symfony\Component\HttpFoundation\File\File;

interface PathGeneratorInterface
{
    /**
     * Some requirements for a path generator:
     *
     * - Generate a new path on each run
     * - Generate a path that will not be caught by ad blockers, i.e. '/ad/fe/image.jpg' or '/fe/gt/ad_image.jpg'
     */
    public function fromFile(File $file): string;
}
