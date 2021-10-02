<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Filesystem;

use Gaufrette\Adapter;

interface AdapterInterface extends Adapter
{
    public function resolveUrl(string $key): string;
}
