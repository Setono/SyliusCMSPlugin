<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Tree\InvalidStoredTreeException;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;

/**
 * Reads and writes the preset file format, which is a contract: code presets are committed in it, and editor presets
 * and exports use it. It's built on the stored block tree format, and written as canonical JSON, a file pretty-printed
 * with four spaces and ending with a newline. A preset with a UUID is an editor preset, which has its changedAt and no
 * thumbnail; one without is a code preset, which always has a thumbnail and whose starting point says whether it's the
 * default. See "Preset file format" in docs/spec/layouts-and-presets.md
 */
final readonly class PresetSerializer
{
    private const DATE_TIME_FORMAT = 'Y-m-d\TH:i:s\Z';

    private const CODE_PRESET_KEYS = ['format', 'name', 'category', 'description', 'thumbnail', 'startingPoint', 'blocks'];

    private const EDITOR_PRESET_KEYS = ['format', 'uuid', 'changedAt', 'name', 'category', 'description', 'startingPoint', 'blocks'];

    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    private StoredTreeSerializer $treeSerializer;

    public function __construct()
    {
        $this->treeSerializer = new StoredTreeSerializer();
    }

    /**
     * The preset as a file
     */
    public function toJson(Preset $preset): string
    {
        return CanonicalJson::encodeFile($this->normalize($preset));
    }

    /**
     * @param string|null $code a code preset's code, its file name without .json
     *
     * @throws InvalidPresetException
     */
    public function fromJson(string $json, ?string $code = null): Preset
    {
        try {
            $data = CanonicalJson::decode($json);
        } catch (\JsonException $e) {
            throw new InvalidPresetException(sprintf('The preset is not valid JSON: %s', $e->getMessage()), previous: $e);
        }

        return $this->denormalize($data, $code);
    }

    public function normalize(Preset $preset): ListedObject
    {
        $editor = !$preset->isFromCode();

        $properties = ['format' => Preset::FORMAT];
        if ($editor) {
            $properties['uuid'] = $preset->uuid;
            $properties['changedAt'] = $preset->changedAt?->setTimezone(new \DateTimeZone('UTC'))->format(self::DATE_TIME_FORMAT);
        }

        $properties['name'] = self::normalizeText($preset->name);
        $properties['category'] = self::normalizeText($preset->category);
        $properties['description'] = self::normalizeText($preset->description);
        if (!$editor) {
            $properties['thumbnail'] = $preset->thumbnail;
        }

        $properties['startingPoint'] = null === $preset->startingPoint ? null : $this->normalizeStartingPoint($preset->startingPoint, $editor);
        $properties['blocks'] = array_map($this->treeSerializer->normalizeBlock(...), $preset->blocks);

        return new ListedObject($properties);
    }

    /**
     * Reads a preset decoded with CanonicalJson::decode()
     *
     * @param string|null $code a code preset's code, its file name without .json
     *
     * @throws InvalidPresetException
     */
    public function denormalize(mixed $data, ?string $code = null): Preset
    {
        $data = self::map($data, 'The preset');
        if (Preset::FORMAT !== ($data['format'] ?? null)) {
            throw new InvalidPresetException(sprintf('The preset must have the format %d', Preset::FORMAT));
        }

        $editor = array_key_exists('uuid', $data);
        $unknown = array_diff(array_keys($data), $editor ? self::EDITOR_PRESET_KEYS : self::CODE_PRESET_KEYS);
        if ([] !== $unknown) {
            throw new InvalidPresetException(sprintf('The preset has a key the format doesn\'t know: "%s"', implode('", "', $unknown)));
        }

        [$uuid, $changedAt] = $editor ? self::editorIdentity($data) : [null, null];
        if (!$editor && !array_key_exists('thumbnail', $data)) {
            throw new InvalidPresetException('A code preset must have a thumbnail, or null');
        }

        $thumbnail = $data['thumbnail'] ?? null;
        if (null !== $thumbnail && !is_string($thumbnail)) {
            throw new InvalidPresetException('The thumbnail of the preset must be a string or null');
        }

        $name = $data['name'] ?? null;
        if (null === $name || '' === $name) {
            throw new InvalidPresetException('The preset must have a name');
        }

        $blocks = $data['blocks'] ?? null;
        if (!is_array($blocks) || !array_is_list($blocks)) {
            throw new InvalidPresetException('The preset must have a list of blocks');
        }

        try {
            $blocks = array_map($this->treeSerializer->denormalizeBlock(...), $blocks);
        } catch (InvalidStoredTreeException $e) {
            throw new InvalidPresetException($e->getMessage(), previous: $e);
        }

        return new Preset(
            self::text($name, 'The name of the preset must be a string or a translation key'),
            $blocks,
            self::nullableText($data['category'] ?? null, 'The category of the preset must be a string, a translation key or null'),
            self::nullableText($data['description'] ?? null, 'The description of the preset must be a string, a translation key or null'),
            $this->denormalizeStartingPoint($data['startingPoint'] ?? null, $editor),
            $editor ? null : $code,
            $thumbnail,
            $uuid,
            $changedAt,
        );
    }

    private function normalizeStartingPoint(StartingPoint $startingPoint, bool $editor): ListedObject
    {
        $properties = [
            'contentType' => $startingPoint->contentType,
            'layout' => null === $startingPoint->layout ? null : $this->treeSerializer->normalizeLayout($startingPoint->layout),
        ];
        if (!$editor) {
            $properties['default'] = $startingPoint->default;
        }

        return new ListedObject($properties);
    }

    /**
     * @throws InvalidPresetException
     */
    private function denormalizeStartingPoint(mixed $data, bool $editor): ?StartingPoint
    {
        if (null === $data) {
            return null;
        }

        $data = self::map($data, 'The starting point');
        $contentType = $data['contentType'] ?? null;
        if (!is_string($contentType) || '' === $contentType) {
            throw new InvalidPresetException('The starting point must have a content type code');
        }

        $default = $editor ? false : ($data['default'] ?? null);
        if (!is_bool($default)) {
            throw new InvalidPresetException('The starting point\'s default must be true or false');
        }

        $layout = $data['layout'] ?? null;

        try {
            return new StartingPoint($contentType, null === $layout ? null : $this->treeSerializer->denormalizeLayout($layout), $default);
        } catch (InvalidStoredTreeException $e) {
            throw new InvalidPresetException(sprintf('The starting point is invalid: %s', $e->getMessage()), previous: $e);
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{string, \DateTimeImmutable}
     *
     * @throws InvalidPresetException
     */
    private static function editorIdentity(array $data): array
    {
        $uuid = $data['uuid'] ?? null;
        $changedAt = $data['changedAt'] ?? null;
        if (!is_string($uuid) || 1 !== preg_match(self::UUID_PATTERN, $uuid) || !is_string($changedAt)) {
            throw new InvalidPresetException('An editor preset must have a UUID and changedAt');
        }

        $parsed = \DateTimeImmutable::createFromFormat('!' . self::DATE_TIME_FORMAT, $changedAt, new \DateTimeZone('UTC'));
        if (false === $parsed || $parsed->format(self::DATE_TIME_FORMAT) !== $changedAt) {
            throw new InvalidPresetException('The changedAt of the preset must be a UTC date and time, such as 2026-11-13T09:30:00Z');
        }

        return [$uuid, $parsed];
    }

    /**
     * @return string|array<string, string>|null
     */
    private static function normalizeText(string|TranslationKey|null $text): string|array|null
    {
        return $text instanceof TranslationKey ? $text->toValue() : $text;
    }

    /**
     * @throws InvalidPresetException
     */
    private static function nullableText(mixed $value, string $error): string|TranslationKey|null
    {
        return null === $value ? null : self::text($value, $error);
    }

    /**
     * @throws InvalidPresetException
     */
    private static function text(mixed $value, string $error): string|TranslationKey
    {
        if (is_string($value)) {
            return $value;
        }

        return TranslationKey::fromValue($value) ?? throw new InvalidPresetException($error);
    }

    /**
     * A JSON object, decoded as an array that isn't a list, or as an empty stdClass when it's empty
     *
     * @return array<string, mixed>
     *
     * @throws InvalidPresetException
     */
    private static function map(mixed $data, string $what): array
    {
        if ($data instanceof \stdClass) {
            $data = get_object_vars($data);
        } elseif (!is_array($data) || array_is_list($data)) {
            // An empty JSON object decodes as an empty stdClass, so an empty array was an empty JSON array
            throw new InvalidPresetException(sprintf('%s must be a JSON object', $what));
        }

        /** @var array<string, mixed> $map */
        $map = $data;

        return $map;
    }
}
