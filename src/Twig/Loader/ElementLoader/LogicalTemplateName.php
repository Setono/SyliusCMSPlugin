<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\Loader\ElementLoader;

use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Webmozart\Assert\Assert;

final class LogicalTemplateName
{
    public const TEMPLATE_NAME_PREFIX = '__sscms';

    /** @readonly */
    public string $type;

    /** @readonly */
    public string $channelCode;

    /** @readonly */
    public string $localeCode;

    /** @readonly */
    public string $code;

    public function __construct(string $type, string $channelCode, string $localeCode, string $code)
    {
        $this->type = $type;
        $this->channelCode = $channelCode;
        $this->localeCode = $localeCode;
        $this->code = $code;
    }

    /**
     * @throws \InvalidArgumentException if the $logicalName is not a valid element template name
     */
    public static function createFromString(string $logicalName): self
    {
        Assert::true(self::isElementTemplate($logicalName), sprintf('The given template name "%s" is not a valid Setono Sylius CMS logical template name', $logicalName));

        [, $type, $channelCode, $localeCode, $code] = explode('/', $logicalName);

        return new self($type, $channelCode, $localeCode, $code);
    }

    public static function createBlockTyped(string $channelCode, string $localeCode, string $code): self
    {
        return new self(ElementInterface::TYPE_BLOCK, $channelCode, $localeCode, $code);
    }

    /**
     * Returns true if the given template name is an CMS element template name
     */
    public static function isElementTemplate(string $name): bool
    {
        return strpos($name, self::TEMPLATE_NAME_PREFIX . '/') === 0;
    }

    /**
     * Returns the string representation of a logical template name, here is an example:
     * __sscms/block/FASHION_WEB/en_US/block1
     */
    public function __toString(): string
    {
        return sprintf(
            '%s/%s/%s/%s/%s',
            self::TEMPLATE_NAME_PREFIX,
            $this->type,
            $this->channelCode,
            $this->localeCode,
            $this->code
        );
    }
}
