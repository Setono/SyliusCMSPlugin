<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem;

use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\PresetProviderInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Asks every preset problem kind about every preset, once per request. Code presets change only with a deploy, and
 * they're read from their files at most once per request, so nothing is stored: the Content badge finds them on every
 * admin page. See "Problems" in docs/spec/admin.md
 */
final class PresetProblemFinder implements PresetProblemFinderInterface, ResetInterface
{
    /** @var list<PresetProblem>|null */
    private ?array $all = null;

    /**
     * @param iterable<PresetProblemKindInterface> $kinds every kind, the services tagged setono_sylius_cms.preset_problem_kind
     */
    public function __construct(
        private readonly PresetProviderInterface $presets,
        private readonly iterable $kinds,
    ) {
    }

    public function findAll(): array
    {
        if (null === $this->all) {
            $all = [];
            foreach ($this->presets->all() as $preset) {
                $all = [...$all, ...$this->findIn($preset)];
            }

            $this->all = $all;
        }

        return $this->all;
    }

    public function findIn(Preset $preset): array
    {
        $errors = $warnings = [];
        foreach ($this->kinds as $kind) {
            foreach ($kind->findIn($preset) as $problem) {
                if (Severity::Error === $problem->severity) {
                    $errors[] = $problem;
                } else {
                    $warnings[] = $problem;
                }
            }
        }

        return [...$errors, ...$warnings];
    }

    public function count(): ProblemCount
    {
        $errors = $warnings = 0;
        foreach ($this->findAll() as $problem) {
            if (Severity::Error === $problem->severity) {
                ++$errors;
            } else {
                ++$warnings;
            }
        }

        return new ProblemCount($errors, $warnings);
    }

    public function reset(): void
    {
        $this->all = null;
    }
}
