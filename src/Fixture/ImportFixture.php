<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Fixture;

use Setono\SyliusCMSPlugin\Transfer\Format\InvalidExportException;
use Setono\SyliusCMSPlugin\Transfer\Import\ExportSources;
use Setono\SyliusCMSPlugin\Transfer\Import\ImporterInterface;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportOptions;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportStatus;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The Sylius fixture setono_cms_import: imports the export folder, or zip, in its `path` option and publishes
 * everything it imported, for demo and test data. An import that refuses, or a path that isn't an export, fails the
 * load with the reasons. See "Fixture" in docs/spec/extending.md and "Who runs it" in docs/spec/transfer.md
 */
final class ImportFixture extends AbstractFixture
{
    public function __construct(
        private readonly ImporterInterface $importer,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getName(): string
    {
        return 'setono_cms_import';
    }

    /**
     * @param array<array-key, mixed> $options
     *
     * @throws \RuntimeException when the import refuses, or the path isn't an export
     */
    public function load(array $options): void
    {
        $path = $options['path'] ?? null;
        if (!is_string($path)) {
            throw new \InvalidArgumentException('The fixture setono_cms_import needs the path of an export folder');
        }

        try {
            $source = ExportSources::open($path);
        } catch (InvalidExportException $e) {
            throw new \RuntimeException($e->getMessage(), previous: $e);
        }

        $result = $this->importer->import($source, new ImportOptions(publish: true));
        if (ImportStatus::Applied === $result->status) {
            return;
        }

        $plan = $result->plan;
        if (null === $plan) {
            throw new \RuntimeException(sprintf('%s couldn\'t be read, so nothing changed: %s', $path, (string) $result->invalid));
        }

        $reasons = [];
        foreach ($plan->conflicts as $conflict) {
            $reasons[] = $conflict->message->trans($this->translator);
        }

        foreach ($plan->refusals as $refusal) {
            $reasons[] = $refusal->reason->trans($this->translator);
        }

        throw new \RuntimeException(sprintf('The import of %s refused, so nothing changed: %s', $path, implode(' ', $reasons)));
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->scalarNode('path')
                    ->info('The export folder, or zip, such as "%kernel.project_dir%/fixtures/cms"')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
            ->end()
        ;
    }
}
