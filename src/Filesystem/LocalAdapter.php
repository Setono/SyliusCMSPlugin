<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Filesystem;

final class LocalAdapter extends AbstractAdapter
{
    public function resolveUrl(string $key): string
    {
        // todo fix
        return 'https://images.unsplash.com/photo-1584824486509-112e4181ff6b?ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&ixlib=rb-1.2.1&auto=format&fit=crop&w=4170&q=80';
    }
}
