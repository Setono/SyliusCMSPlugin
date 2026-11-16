<?php

declare(strict_types=1);

use Setono\SyliusCMSPlugin\Tests\Application\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__ . '/../../vendor/autoload.php';

// Booting the kernel needs the test app's env vars, which its .env has. Env vars already set, such as an exported
// DATABASE_URL, win
(new Dotenv())->load(__DIR__ . '/../Application/.env');

$kernel = new Kernel('test', true);
$kernel->boot();

/*
 * PHPStan loads every command it's handed, to read their options, so it gets only the plugin's own. Loading all of the
 * application's breaks on the lowest dependencies: symfony/translation 6.4.0's PHP extractor builds its parser with a
 * php-parser 4 method that the php-parser 5 inside PHPStan doesn't have
 */
return new class($kernel) extends Application {
    /**
     * @return Command[]
     */
    public function all(?string $namespace = null): array
    {
        return array_filter(parent::all($namespace), static fn (string $name): bool => str_starts_with($name, 'setono:'), \ARRAY_FILTER_USE_KEY);
    }
};
