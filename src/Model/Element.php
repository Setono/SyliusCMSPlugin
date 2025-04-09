<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Component\Resource\Model\TimestampableTrait;
use function Symfony\Component\String\u;

abstract class Element implements ElementInterface
{
    use InternalDescriptionAwareTrait;
    use TimestampableTrait;

    protected ?int $id = null;

    protected ?string $code = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    /**
     * @param ElementInterface|class-string<ElementInterface> $element
     */
    public static function getType(ElementInterface|string $element = null, string $separator = '_'): string
    {
        if (null === $element) {
            $element = static::class;
        }

        $type = u((new \ReflectionClass($element))->getShortName())->snake();

        if ('_' !== $separator) {
            $type = $type->replace('_', $separator);
        }

        return $type->toString();
    }
}
