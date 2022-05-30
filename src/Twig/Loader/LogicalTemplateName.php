<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\Loader;

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
        $parts = explode('/', $logicalName, 5);
        Assert::count($parts, 5, sprintf('The logical template name MUST contain 5 parts (namespace, type, channelCode, localeCode, and code). The given template name "%s" did not resolve to 5 parts.', $logicalName));
        Assert::allStringNotEmpty($parts, sprintf('The 5 parts of the logical template name MUST all be non empty strings. Given input was: "%s".', $logicalName));

        [$namespace, $type, $channelCode, $localeCode, $code] = $parts;

        Assert::same($namespace, self::TEMPLATE_NAME_PREFIX, sprintf('The first part of the logical template name is the namespace and has to be exactly "%s". Given namespace was: %s', self::TEMPLATE_NAME_PREFIX, $namespace));

        return new self($type, $channelCode, $localeCode, $code);
    }

    public static function createBlockTyped(string $channelCode, string $localeCode, string $code): self
    {
        return new self(ElementInterface::TYPE_BLOCK, $channelCode, $localeCode, $code);
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
