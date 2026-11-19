<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\EditorPresetInterface;

/**
 * Reads an editor preset's row as a preset, and writes a preset to one: its content in the preset file format, as
 * canonical JSON, and its UUID and changedAt both in the content and in their columns, so the two always agree
 *
 * @internal
 */
final readonly class EditorPresetContent
{
    private PresetSerializer $serializer;

    public function __construct()
    {
        $this->serializer = new PresetSerializer();
    }

    /**
     * @throws InvalidPresetException when the content isn't a preset, or is another's
     */
    public function read(EditorPresetInterface $row): Preset
    {
        $preset = $this->serializer->fromJson($row->getContent());
        if ($preset->uuid !== $row->getUuid()) {
            throw new InvalidPresetException(sprintf('The content of the editor preset %s is the preset %s', $row->getUuid(), $preset->uuid ?? 'without a UUID'));
        }

        return $preset;
    }

    /**
     * Writes the preset to the row, with the row's UUID and the preset's changedAt
     */
    public function write(EditorPresetInterface $row, Preset $preset): void
    {
        $changedAt = $preset->changedAt ?? throw new \InvalidArgumentException('An editor preset needs its changedAt');

        $row->setChangedAt($changedAt);
        $row->setContent(CanonicalJson::encode($this->serializer->normalize(new Preset(
            $preset->name,
            $preset->blocks,
            $preset->category,
            $preset->description,
            $preset->startingPoint,
            uuid: $row->getUuid(),
            changedAt: $row->getChangedAt(),
        ))));
    }
}
