<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\EmbedCodeOffKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\TemporaryDirectory;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * While setono_sylius_cms.embed_code is off, an import refuses an embed_code block that isn't in the entry's published
 * tree here with the same key, or whose code differs from that one's, as publish does. See "Code the target must have"
 * in docs/spec/transfer.md and "Embed code" in docs/spec/blocks.md
 */
final class ImportEmbedCodeTest extends KernelTestCase
{
    use Entries;
    use TemporaryDirectory;

    private const FIXTURE = __DIR__ . '/../Fixture/export';

    private const PAGE = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e';

    private const EMBED_CODE = '0193a5be-0000-7000-8000-0000000000e1';

    private const CODE = '<div id="reviews"></div>';

    private const REFUSED = 'Embed code is switched off in this shop, so Autumn sale can\'t bring in an Embed code block that isn\'t published here with the same key and code.';

    protected static function getKernelClass(): string
    {
        return EmbedCodeOffKernel::class;
    }

    /**
     * @test
     */
    public function it_refuses_an_embed_code_block_the_entry_has_not_published_here(): void
    {
        $tester = self::import($this->exportWithEmbedCode(self::CODE));

        self::assertSame(Command::FAILURE, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString(self::REFUSED, self::display($tester));
    }

    /**
     * @test
     */
    public function it_imports_an_embed_code_block_the_entry_has_published_here_with_the_same_key_and_code(): void
    {
        $export = $this->exportWithEmbedCode(self::CODE);
        self::publishedPageWithEmbedCode(self::CODE);

        $tester = self::import($export);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Autumn sale (page): update', self::display($tester));
    }

    /**
     * @test
     */
    public function it_refuses_an_embed_code_block_whose_code_differs_from_the_one_published_here(): void
    {
        $export = $this->exportWithEmbedCode('<script src="https://evil.example/skim.js"></script>');
        self::publishedPageWithEmbedCode(self::CODE);

        $tester = self::import($export);

        self::assertSame(Command::FAILURE, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString(self::REFUSED, self::display($tester));
    }

    /**
     * The page with the export's UUID, published here with an Embed code block from when embed_code was on
     */
    private static function publishedPageWithEmbedCode(string $code): void
    {
        $page = self::createPageWithBlocks('Autumn sale (old)', 'autumn-sale', [
            new StoredBlock(self::EMBED_CODE, 'embed_code', 1, fields: ['code' => $code]),
        ], published: true, channels: ['WEB']);
        // Published before the export's copy, so it isn't stale
        self::entityManager()->getConnection()->update('setono_sylius_cms__entry', ['uuid' => self::PAGE, 'changed_at' => '2026-11-01 00:00:00'], ['id' => $page->getId()]);
        self::entityManager()->clear();
    }

    /**
     * A copy of the committed export whose page has an Embed code block after its blocks
     */
    private function exportWithEmbedCode(string $code): string
    {
        $export = $this->temporaryDirectory() . '/export';
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::FIXTURE, \FilesystemIterator::SKIP_DOTS)) as $file) {
            \assert($file instanceof \SplFileInfo);
            $copy = $export . substr($file->getPathname(), strlen(self::FIXTURE));
            if (!is_dir(dirname($copy))) {
                mkdir(dirname($copy), 0777, true);
            }

            copy($file->getPathname(), $copy);
        }

        $path = sprintf('%s/entries/%s.json', $export, self::PAGE);
        /** @var array{entry: array{tree: array{blocks: list<mixed>}}} $data */
        $data = CanonicalJson::decode((string) file_get_contents($path));
        $data['entry']['tree']['blocks'][] = ['key' => self::EMBED_CODE, 'type' => 'embed_code', 'version' => 1, 'style' => null, 'fields' => ['code' => $code], 'translated' => new \stdClass(), 'conditions' => [], 'children' => []];
        file_put_contents($path, CanonicalJson::encodeFile($data));

        return $export;
    }

    private static function import(string $path): CommandTester
    {
        self::bootKernel();
        $tester = new CommandTester((new Application(self::$kernel))->find('setono:cms:import'));
        $tester->execute(['path' => $path], ['decorated' => false]);

        return $tester;
    }

    private static function display(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }
}
