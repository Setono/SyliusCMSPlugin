<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\Loader\ElementLoader;

use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Webmozart\Assert\Assert;

final class LogicalTemplateName
{
    public const TEMPLATE_NAME_PREFIX = '__sscms';

    /** @readonly */
    public string $code;

    /** @readonly */
    public string $channelCode;

    /** @readonly */
    public string $localeCode;

    /** @readonly */
    public string $type;

    public function __construct(string $code, string $channelCode, string $localeCode, string $type)
    {
        $this->code = $code;
        $this->channelCode = $channelCode;
        $this->localeCode = $localeCode;
        $this->type = $type;
    }

    /**
     * @throws \InvalidArgumentException if the $logicalName is not a valid element template name
     */
    public static function createFromString(string $logicalName): self
    {
        Assert::true(self::isElementTemplate($logicalName));

        [$code, $channelCode, $localeCode, $type] = explode('/', $logicalName);

        return new self($code, $channelCode, $localeCode, $type);
    }

    public static function createBlockTyped(string $code, string $channelCode, string $localeCode): self
    {
        return new self($code, $channelCode, $localeCode, ElementInterface::TYPE_BLOCK);
    }

    /**
     * Returns true if the given template name is an CMS element template name
     */
    public static function isElementTemplate(string $name): bool
    {
        return strpos($name, self::TEMPLATE_NAME_PREFIX) === 0;
    }

    public function __toString(): string
    {
        return sprintf('%s/%s/%s/%s/%s',
            self::TEMPLATE_NAME_PREFIX, $this->code, $this->channelCode, $this->localeCode, $this->type);
    }
}
