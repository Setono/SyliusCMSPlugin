<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\SwitchedOffKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * What a project switches off in its config: setono_sylius_cms.assets and setono_sylius_cms.disabled.block_types
 */
final class SwitchingOffWhatShipsTest extends WebTestCase
{
    use Entries;

    /**
     * @test
     */
    public function it_does_not_load_the_stylesheet_in_the_shop_when_assets_is_off(): void
    {
        $client = self::createClient();

        $client->request('GET', '/en_US/');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('link[href*="setonosyliuscmsplugin/shop/cms.css"]');
    }

    /**
     * @test
     */
    public function it_marks_a_disabled_block_type_for_the_picker_to_leave_out(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        $client->request('GET', '/admin/cms/api/registry', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseIsSuccessful();
        /** @var array{blockTypes: list<array{code: string, disabled: bool}>} $registry */
        $registry = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $disabled = array_column($registry['blockTypes'], 'disabled', 'code');

        self::assertTrue($disabled['spacer']);
        self::assertFalse($disabled['heading']);
    }

    /**
     * @test
     */
    public function it_keeps_rendering_a_block_of_a_disabled_block_type(): void
    {
        $client = self::createClient();
        self::createPageWithBlocks('Spaced', 'spaced', [
            self::heading('0193a5be-0000-7000-8000-000000000001', 'Above'),
            new StoredBlock('0193a5be-0000-7000-8000-000000000002', 'spacer', 1, fields: ['size' => 'small']),
        ], published: true);

        $client->request('GET', '/en_US/spaced');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.cms-tree > .cms-heading + .cms-spacer.cms-spacer--small');
    }

    /**
     * @param array<array-key, mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new SwitchedOffKernel('test', (bool) ($_SERVER['APP_DEBUG'] ?? true));
    }
}
