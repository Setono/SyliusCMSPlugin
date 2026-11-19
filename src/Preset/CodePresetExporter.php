<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

use Setono\SyliusCMSPlugin\Field\Media;
use Setono\SyliusCMSPlugin\Field\MediaList;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistryInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Turns a preset into a code preset file, which is how developers compose code presets: they save one as an editor
 * preset in the builder and export it, from the Presets tab or with setono:cms:preset:export. The file is named by the
 * preset's name in snake case, leaves out the editor preset's UUID and changedAt, and has no thumbnail. Media and Media
 * list fields are left empty, in the blocks and in a starting point's layout, because media items exist only in one
 * environment's library: the block's placeholder shows in their place. Text stays as locale maps, which a plugin replaces
 * with translation keys. A block of a type the code no longer has is left as it is. See "Code presets" in
 * docs/spec/layouts-and-presets.md and ADR 0012
 */
final readonly class CodePresetExporter
{
    /** The code of a preset whose name gives none, and the prefix of one whose name starts with a digit */
    private const FALLBACK_CODE = 'preset';

    public function __construct(
        private BlockTypeRegistryInterface $blockTypes,
        private LayoutRegistryInterface $layouts,
    ) {
    }

    public function export(Preset $preset): CodePresetFile
    {
        $code = self::codeOf($preset->name instanceof TranslationKey ? $preset->name->key : $preset->name);
        $startingPoint = $preset->startingPoint;
        if (null !== $startingPoint) {
            $startingPoint = new StartingPoint($startingPoint->contentType, null === $startingPoint->layout ? null : $this->layoutWithoutMedia($startingPoint->contentType, $startingPoint->layout));
        }

        return new CodePresetFile($code, (new PresetSerializer())->toJson(new Preset(
            $preset->name,
            array_map($this->withoutMedia(...), $preset->blocks),
            $preset->category,
            $preset->description,
            $startingPoint,
            $code,
        )));
    }

    /**
     * A code from a preset's name: in snake case, with only ASCII letters, digits and underscores, and starting with a
     * letter, so it matches ^[a-z][a-z0-9_]*$
     */
    public static function codeOf(string $name): string
    {
        // ASCII words, each camel case word apart, joined by underscores
        $words = (new AsciiSlugger())->slug($name, ' ')->replaceMatches('/(?<=[a-z0-9])(?=[A-Z])/', ' ')->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
        if ('' === $words) {
            return self::FALLBACK_CODE;
        }

        return 1 === preg_match('/^[a-z]/', $words) ? $words : self::FALLBACK_CODE . '_' . $words;
    }

    private function withoutMedia(StoredBlock $block): StoredBlock
    {
        $copy = clone $block;
        if ($this->blockTypes->has($block->type)) {
            [$copy->fields, $copy->translated] = self::emptied($this->blockTypes->get($block->type)->fields, $block->fields, $block->translated);
        }

        $copy->children = array_map($this->withoutMedia(...), $block->children);

        return $copy;
    }

    private function layoutWithoutMedia(string $contentType, StoredLayout $layout): StoredLayout
    {
        if (!$this->layouts->has($contentType, $layout->code)) {
            return $layout;
        }

        [$fields, $translated] = self::emptied($this->layouts->get($contentType, $layout->code)->fields, $layout->fields, $layout->translated);

        return new StoredLayout($layout->code, $layout->version, $fields, $translated);
    }

    /**
     * The values and the translation state without those of the media fields
     *
     * @param array<string, FieldMetadata> $fields
     * @param array<string, mixed> $values
     * @param array<string, mixed> $translated
     *
     * @return array{array<string, mixed>, array<string, mixed>}
     */
    private static function emptied(array $fields, array $values, array $translated): array
    {
        foreach ($fields as $name => $field) {
            if ($field->field instanceof Media || $field->field instanceof MediaList) {
                unset($values[$name], $translated[$name]);
            }
        }

        return [$values, $translated];
    }
}
