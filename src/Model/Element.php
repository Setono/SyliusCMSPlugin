<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Component\Resource\Model\TimestampableTrait;

abstract class Element implements ElementInterface
{
    use CodeAwareTrait;

    use IdAwareTrait;

    use InternalDescriptionAwareTrait;

    use TagsAwareTrait {
        __construct as private initializeTagsCollection;
    }

    use TimestampableTrait;

    public function __construct()
    {
        $this->initializeTagsCollection();
    }

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

    public function getIdentifier(): string
    {
        return sprintf('sscms-%s-%s', $this->getType(), (string) $this->getCode());
    }
}
