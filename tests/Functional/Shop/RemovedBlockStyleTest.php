<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\SeasonalStyleKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * A block keeps the code of its style when a project removes the style from its config. See "Block styles" in docs/spec/blocks.md
 */
final class RemovedBlockStyleTest extends WebTestCase
{
    use Entries;

    /** Whether the app's config still has the Group's seasonal style */
    private static bool $seasonalStyle = true;

    protected function tearDown(): void
    {
        parent::tearDown();

        self::$seasonalStyle = true;
    }

    /**
     * @test
     */
    public function a_group_whose_style_was_removed_from_the_config_renders_plain(): void
    {
        $client = self::createClient();
        self::createPageWithBlocks('Seasonal', 'seasonal', [
            new StoredBlock('0193a5be-0000-7000-8000-000000000001', 'group', 1, style: 'seasonal', children: [self::heading('0193a5be-0000-7000-8000-000000000002', 'Winter sale')]),
        ], published: true);

        $crawler = $client->request('GET', '/en_US/seasonal');
        self::assertResponseIsSuccessful();
        self::assertSame('cms-group app-group--seasonal', $crawler->filter('.cms-tree > .cms-group')->attr('class'));

        // The season is over, and the project takes the style out of its config
        self::ensureKernelShutdown();
        self::$seasonalStyle = false;
        $client = self::createClient();

        $crawler = $client->request('GET', '/en_US/seasonal');
        self::assertResponseIsSuccessful();
        self::assertSame('cms-group', $crawler->filter('.cms-tree > .cms-group')->attr('class'));
        self::assertSelectorTextSame('.cms-tree > .cms-group .cms-heading', 'Winter sale');
    }

    /**
     * @param array<array-key, mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return self::$seasonalStyle ? new SeasonalStyleKernel('test', (bool) ($_SERVER['APP_DEBUG'] ?? true)) : parent::createKernel($options);
    }
}
