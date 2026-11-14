<?php

declare(strict_types=1);

use Setono\SyliusCMSPlugin\Tests\Application\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__ . '/../../vendor/autoload.php';

// Analysing a command loads every command of the application, and some of Sylius's need env vars, such as MAILER_DSN.
// The test app's .env has them. Env vars already set, such as an exported DATABASE_URL, win
(new Dotenv())->load(__DIR__ . '/../Application/.env');

$kernel = new Kernel('test', true);
$kernel->boot();

return new Application($kernel);
