<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Condition;

use Setono\SyliusCMSPlugin\Registry\Code;

/**
 * Reads a condition in the stored condition format: {"kind": a condition kind's code, "negate": true or false,
 * "params": a map of parameter values}. See "Visibility conditions" in docs/spec/content-model.md
 *
 * @internal
 */
final class StoredCondition
{
    private function __construct()
    {
    }

    /**
     * The condition, or null when the value isn't one. An empty JSON object, as CanonicalJson::decode() reads it, is
     * empty parameters
     *
     * @return array{kind: string, negate: bool, params: array<string, mixed>}|null
     */
    public static function fromValue(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $kind = $value['kind'] ?? null;
        $negate = $value['negate'] ?? null;
        $params = $value['params'] ?? null;
        if ($params instanceof \stdClass) {
            $params = get_object_vars($params);
        }

        if (!is_string($kind) || !Code::isValid($kind) || !is_bool($negate) || !is_array($params) || ([] !== $params && array_is_list($params))) {
            return null;
        }

        /** @var array<string, mixed> $params */
        return ['kind' => $kind, 'negate' => $negate, 'params' => $params];
    }
}
