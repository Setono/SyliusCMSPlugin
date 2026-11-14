<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture;

use Setono\SyliusCMSPlugin\Upgrade\UpgraderInterface;

/**
 * An upgrader that renames one field, as a project's upgrader does when a field is renamed. It keeps the data it got
 */
final class RenameField implements UpgraderInterface
{
    /** @var list<array<array-key, mixed>> */
    public array $received = [];

    public function __construct(
        private readonly string $from,
        private readonly string $to,
    ) {
    }

    public function upgrade(array $data): array
    {
        $this->received[] = $data;

        /** @var array<string, mixed> $fields */
        $fields = $data['fields'];
        if (array_key_exists($this->from, $fields)) {
            $fields[$this->to] = $fields[$this->from];
            unset($fields[$this->from]);
        }

        $data['fields'] = $fields;

        return $data;
    }
}
