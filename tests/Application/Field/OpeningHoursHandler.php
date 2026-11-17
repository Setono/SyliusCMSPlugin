<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Application\Field;

use Setono\SyliusCMSPlugin\Field\Field;
use Setono\SyliusCMSPlugin\Field\FieldHandlerInterface;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Tests\Application\Value\OpeningHours as OpeningHoursValue;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles the test application's opening hours fields. The stored value is {"days": {"monday": {"opens": "09:00",
 * "closes": "17:00"}, …}, "photo": "<media item UUID>"}, where a day that isn't there is closed. The photo is a reference
 * to a media item, which loads before rendering and is indexed, like any other
 */
final readonly class OpeningHoursHandler implements FieldHandlerInterface
{
    public const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    private const TIME_PATTERN = '^([01][0-9]|2[0-3]):[0-5][0-9]$';

    private const MEDIA_ITEM = 'media_item';

    public function __construct(private TranslatorInterface $translator)
    {
    }

    public function schema(Field $field): array
    {
        $time = ['type' => 'string', 'pattern' => self::TIME_PATTERN];

        return [
            'type' => 'object',
            'properties' => [
                'days' => [
                    'type' => 'object',
                    'propertyNames' => ['enum' => self::DAYS],
                    'additionalProperties' => [
                        'type' => 'object',
                        'properties' => ['opens' => $time, 'closes' => $time],
                        'required' => ['opens', 'closes'],
                        'additionalProperties' => false,
                    ],
                ],
                'photo' => ['type' => 'string', 'format' => 'uuid'],
            ],
            'additionalProperties' => false,
            // The field control's own strings, translated in the admin user's locale. It takes the builder's own strings,
            // such as the media picker's buttons, from .builder.trans()
            'x-cms-options' => [
                'days' => array_combine(self::DAYS, array_map($this->dayName(...), self::DAYS)),
                'strings' => [
                    'opens' => $this->translator->trans('app.cms.opening_hours.opens'),
                    'closes' => $this->translator->trans('app.cms.opening_hours.closes'),
                    'photo' => $this->translator->trans('app.cms.opening_hours.photo'),
                ],
            ],
        ];
    }

    public function hydrate(Field $field, mixed $value, HydrationContext $context): ?OpeningHoursValue
    {
        if (!is_array($value)) {
            return null;
        }

        $days = [];
        foreach (self::DAYS as $day) {
            $hours = self::hoursOn($value, $day);
            if (null !== $hours && self::isTime($hours['opens']) && self::isTime($hours['closes'])) {
                $days[$day] = ['opens' => $hours['opens'], 'closes' => $hours['closes']];
            }
        }

        $photo = self::photoOf($value);
        $mediaItem = null === $photo ? null : $context->references->get(self::MEDIA_ITEM, strtolower($photo));

        return new OpeningHoursValue($days, $mediaItem instanceof MediaItemInterface ? $mediaItem : null);
    }

    public function references(Field $field, mixed $value): iterable
    {
        $photo = is_array($value) ? self::photoOf($value) : null;

        // A media item is a dependency: an export brings it along
        return null === $photo ? [] : [new Reference(self::MEDIA_ITEM, strtolower($photo), true)];
    }

    public function validate(Field $field, mixed $value, ExecutionContextInterface $context): void
    {
        if (null === $value) {
            return;
        }

        if (!is_array($value) || [] !== array_diff(array_keys($value), ['days', 'photo']) || (isset($value['days']) && !is_array($value['days']))) {
            $context->buildViolation('app.cms.opening_hours.invalid')->addViolation();

            return;
        }

        $days = $value['days'] ?? [];
        foreach (array_keys($days) as $day) {
            if (!in_array($day, self::DAYS, true)) {
                $context->buildViolation('app.cms.opening_hours.unknown_day')->setParameter('%day%', (string) $day)->addViolation();
            }
        }

        foreach (self::DAYS as $day) {
            if (!array_key_exists($day, $days)) {
                continue;
            }

            $hours = self::hoursOn($value, $day);
            if (null === $hours || !self::isTime($hours['opens']) || !self::isTime($hours['closes'])) {
                $context->buildViolation('app.cms.opening_hours.invalid_time')->setParameter('%day%', $this->dayName($day))->addViolation();
            } elseif ($hours['closes'] <= $hours['opens']) {
                $context->buildViolation('app.cms.opening_hours.closes_before_opens')->setParameter('%day%', $this->dayName($day))->addViolation();
            }
        }

        if (isset($value['photo']) && null === self::photoOf($value)) {
            $context->buildViolation('setono_sylius_cms.field.not_media_item')->addViolation();
        }
    }

    private function dayName(string $day): string
    {
        return $this->translator->trans(sprintf('app.cms.opening_hours.%s', $day));
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @return array{opens: mixed, closes: mixed}|null
     */
    private static function hoursOn(array $value, string $day): ?array
    {
        $hours = is_array($value['days'] ?? null) ? $value['days'][$day] ?? null : null;

        return is_array($hours) && [] === array_diff(array_keys($hours), ['opens', 'closes']) ? ['opens' => $hours['opens'] ?? null, 'closes' => $hours['closes'] ?? null] : null;
    }

    /**
     * @phpstan-assert-if-true string $time
     */
    private static function isTime(mixed $time): bool
    {
        return is_string($time) && 1 === preg_match('/' . self::TIME_PATTERN . '/', $time);
    }

    /**
     * @param array<array-key, mixed> $value
     */
    private static function photoOf(array $value): ?string
    {
        $photo = $value['photo'] ?? null;

        return is_string($photo) && Uuid::isValid($photo) ? $photo : null;
    }
}
