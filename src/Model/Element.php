<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

abstract class Element implements ElementInterface
{
    use InternalDescriptionAwareTrait;

    protected ?int $id = null;

    protected ?string $code = null;

    /**
     * @return list<string>
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_BLOCK,
            self::TYPE_CAROUSEL,
            self::TYPE_PAGE,
            self::TYPE_VIEW,
        ];
    }

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
}
