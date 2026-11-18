<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Checks that what block types, condition kinds and sources need are context keys setono_sylius_cms.context_keys
 * declares, so a typo or a key removed from the config fails the container build. Without the parameter, such as in a
 * pass's own test, it checks nothing
 *
 * @internal
 */
final class DeclaredContextKeys
{
    public const PARAMETER = 'setono_sylius_cms.context_keys';

    private function __construct()
    {
    }

    /**
     * @param list<string> $needs
     * @param string $what what needs them, for the error, such as "The block type App\Block\Hero"
     *
     * @throws \LogicException
     */
    public static function assert(ContainerBuilder $container, array $needs, string $what): void
    {
        if (!$container->hasParameter(self::PARAMETER)) {
            return;
        }

        $declared = $container->getParameter(self::PARAMETER);
        foreach ($needs as $key) {
            if (!is_array($declared) || !isset($declared[$key])) {
                throw new \LogicException(sprintf('%s needs the context key "%s", which isn\'t declared under %s', $what, $key, self::PARAMETER));
            }
        }
    }
}
