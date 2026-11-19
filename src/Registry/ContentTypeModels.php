<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

/**
 * How a class names a content type, as a collection's categoryType or an Entries field's contentType does: by the
 * content type's model, or else by a class or interface the model extends or implements, such as the shipped model a
 * project's model of a shipped content type extends. The one rule the registry, the build and the entry lists follow
 *
 * @internal
 */
final class ContentTypeModels
{
    private function __construct()
    {
    }

    /**
     * The model the class names among the models: the one that's the class, or else the first that extends or
     * implements it
     *
     * @param array<array-key, class-string> $models
     *
     * @return class-string|null
     */
    public static function namedBy(string $class, array $models): ?string
    {
        $code = self::codeNamedBy($class, $models);

        return null === $code ? null : $models[$code];
    }

    /**
     * The key, such as the content type's code, of the model the class names among the models
     *
     * @template TKey of array-key
     *
     * @param array<TKey, class-string> $models
     *
     * @return TKey|null
     */
    public static function codeNamedBy(string $class, array $models): int|string|null
    {
        $code = array_search($class, $models, true);
        if (false !== $code) {
            return $code;
        }

        foreach ($models as $code => $model) {
            if (is_a($model, $class, true)) {
                return $code;
            }
        }

        return null;
    }
}
