<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Fixture;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Fixture\ImportFixture;
use Setono\SyliusCMSPlugin\Transfer\Import\ConflictKind;
use Setono\SyliusCMSPlugin\Transfer\Import\DirectorySource;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportConflict;
use Setono\SyliusCMSPlugin\Transfer\Import\ImporterInterface;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportOptions;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportPlan;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportRefusal;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportResult;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ImportFixtureTest extends TestCase
{
    use ProphecyTrait;

    private const EXPORT = __DIR__ . '/../../Fixture/export';

    /**
     * @test
     */
    public function it_is_the_sylius_fixture_setono_cms_import_with_a_path(): void
    {
        $fixture = new ImportFixture($this->prophesize(ImporterInterface::class)->reveal(), new IdentityTranslator());

        self::assertSame('setono_cms_import', $fixture->getName());
        self::assertSame(['path' => '/fixtures/cms'], (new Processor())->processConfiguration($fixture, [['path' => '/fixtures/cms']]));
    }

    /**
     * @test
     */
    public function it_imports_the_export_folder_publishing_everything_it_imports(): void
    {
        $importer = $this->prophesize(ImporterInterface::class);
        $importer->import(Argument::type(DirectorySource::class), Argument::that(
            static fn (ImportOptions $options): bool => $options->publish && !$options->dryRun && !$options->overwrite,
        ))->willReturn(ImportResult::applied(new ImportPlan([]), false))->shouldBeCalledOnce();

        (new ImportFixture($importer->reveal(), new IdentityTranslator()))->load(['path' => self::EXPORT]);
    }

    /**
     * @test
     */
    public function it_fails_the_load_with_the_conflicts_and_reasons_of_an_import_that_refuses(): void
    {
        $importer = $this->prophesize(ImporterInterface::class);
        $importer->import(Argument::cetera())->willReturn(ImportResult::refused(new ImportPlan(
            [],
            [new ImportConflict(ConflictKind::Draft, 'a1', new TranslatableMessage('setono_sylius_cms.import.conflict.draft', ['%title%' => 'Autumn sale']))],
            [new ImportRefusal(null, new TranslatableMessage('setono_sylius_cms.import.path_outside_root', ['%path%' => '../evil']))],
        )));
        $translator = $this->prophesize(TranslatorInterface::class);
        $translator->trans('setono_sylius_cms.import.conflict.draft', ['%title%' => 'Autumn sale'], null, null)->willReturn('Autumn sale has unpublished changes here.');
        $translator->trans('setono_sylius_cms.import.path_outside_root', ['%path%' => '../evil'], null, null)->willReturn('The export holds ../evil, which isn\'t inside its folder.');

        $this->expectExceptionObject(new \RuntimeException(sprintf('The import of %s refused, so nothing changed: Autumn sale has unpublished changes here. The export holds ../evil, which isn\'t inside its folder.', self::EXPORT)));

        (new ImportFixture($importer->reveal(), $translator->reveal()))->load(['path' => self::EXPORT]);
    }

    /**
     * @test
     */
    public function it_fails_the_load_for_an_export_it_cannot_read(): void
    {
        $importer = $this->prophesize(ImporterInterface::class);
        $importer->import(Argument::cetera())->willReturn(ImportResult::invalid('entries/x.json isn\'t in the export format'));

        $this->expectExceptionObject(new \RuntimeException(sprintf('%s couldn\'t be read, so nothing changed: entries/x.json isn\'t in the export format', self::EXPORT)));

        (new ImportFixture($importer->reveal(), new IdentityTranslator()))->load(['path' => self::EXPORT]);
    }

    /**
     * The options node requires a path, but takes any scalar, such as a number
     *
     * @test
     */
    public function it_fails_the_load_without_the_path_of_an_export(): void
    {
        $importer = $this->prophesize(ImporterInterface::class);
        $importer->import(Argument::cetera())->shouldNotBeCalled();
        $fixture = new ImportFixture($importer->reveal(), new IdentityTranslator());

        $this->expectExceptionObject(new \InvalidArgumentException('The fixture setono_cms_import needs the path of an export folder'));

        $fixture->load(['path' => 42]);
    }

    /**
     * @test
     */
    public function it_fails_the_load_for_a_path_that_is_not_an_export(): void
    {
        $importer = $this->prophesize(ImporterInterface::class);
        $importer->import(Argument::cetera())->shouldNotBeCalled();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('/nowhere is neither an export folder nor a zip');

        (new ImportFixture($importer->reveal(), new IdentityTranslator()))->load(['path' => '/nowhere']);
    }
}
