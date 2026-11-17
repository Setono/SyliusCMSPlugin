<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Condition;

use Setono\SyliusCMSPlugin\Registry\Code;

/**
 * A condition in the stored condition format: {"kind": a condition kind's code, "negate": true or false,
 * "params": a map of parameter values}. See "Visibility conditions" in docs/spec/content-model.md
 *
 * @internal
 */
final readonly class StoredCondition
{
    /**
     * @param string $kind the code of its condition kind
     * @param bool $negate whether it's "is not one of" rather than "is one of"
     * @param array<string, mixed> $params the parameter values by name
     */
    public function __construct(
        public string $kind,
        public bool $negate,
        public array $params,
    ) {
    }

    /**
     * The condition, or null when the value isn't one. An empty JSON object, as CanonicalJson::decode() reads it, is
     * empty parameters
     */
    public static function fromValue(mixed $value): ?self
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

        /** @var array<string, mixed> $map */
        $map = $params;

        return new self($kind, $negate, $map);
    }

    /**
     * In the stored condition format
     *
     * @return array{kind: string, negate: bool, params: array<string, mixed>}
     */
    public function toArray(): array
    {
        return ['kind' => $this->kind, 'negate' => $this->negate, 'params' => $this->params];
    }
}
