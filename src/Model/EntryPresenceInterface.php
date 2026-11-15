<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Resource\Model\ResourceInterface;

/**
 * An admin user who has an entry open in the builder, as of the last time their builder said so. See "Saving" in
 * docs/spec/publishing.md
 */
interface EntryPresenceInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getEntry(): EntryInterface;

    /**
     * The admin user's identifier
     */
    public function getEditor(): string;

    /**
     * The admin user's name, as the builder shows it
     */
    public function getName(): string;

    public function getSeenAt(): \DateTimeImmutable;

    /**
     * The admin user's builder said again that they have the entry open
     */
    public function see(string $name, \DateTimeImmutable $seenAt): void;
}
