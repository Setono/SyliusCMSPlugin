<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The code presets: the files the container build found in setono_sylius_cms.presets.paths (CodePresetFiles), without
 * those setono_sylius_cms.disabled.presets switches off. Each file is read when it's first needed, once per request. A
 * file that isn't in the preset file format is left out and logged: setono:cms:preset:validate reports it in CI. See
 * "Code presets" in docs/spec/layouts-and-presets.md
 */
final class CodePresetProvider implements PresetProviderInterface, ResetInterface
{
    /** @var array<string, Preset|null> code => the preset read from its file, or null when it can't be read */
    private array $read = [];

    private readonly PresetSerializer $serializer;

    /**
     * @param array<string, string> $files code => the path of its file
     * @param list<string> $disabled the codes switched off
     */
    public function __construct(
        private readonly array $files,
        private readonly array $disabled,
        private readonly LoggerInterface $logger,
    ) {
        $this->serializer = new PresetSerializer();
    }

    public function all(): array
    {
        $presets = [];
        foreach (array_keys($this->files) as $code) {
            $preset = $this->find($code);
            if (null !== $preset) {
                $presets[] = $preset;
            }
        }

        return $presets;
    }

    public function find(string $id): ?Preset
    {
        if (!isset($this->files[$id]) || in_array($id, $this->disabled, true)) {
            return null;
        }

        if (!array_key_exists($id, $this->read)) {
            $this->read[$id] = $this->read($id, $this->files[$id]);
        }

        return $this->read[$id];
    }

    public function reset(): void
    {
        $this->read = [];
    }

    private function read(string $code, string $file): ?Preset
    {
        try {
            $json = @file_get_contents($file);
            if (false === $json) {
                throw new InvalidPresetException('The file can\'t be opened');
            }

            return $this->serializer->fromJson($json, $code);
        } catch (InvalidPresetException $e) {
            $this->logger->warning('The code preset {code} can\'t be read, so editors aren\'t offered it: {message}', [
                'code' => $code,
                'file' => $file,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
