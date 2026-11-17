<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Resource\Model\ResourceInterface;

/**
 * A row of the bestseller scores: how well one product sells in one channel, higher selling better, as the bestsellers
 * calculator last scored it. setono:cms:bestsellers:compute replaces a channel's rows, and the Bestsellers source joins
 * the products a shopper may see against the channel's. Channels and products are named by code, as the calculator
 * names them, so the rows are written without loading either. See "Bestsellers" in docs/spec/sources.md
 */
interface BestsellerScoreInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getChannelCode(): string;

    public function setChannelCode(string $channelCode): void;

    public function getProductCode(): string;

    public function setProductCode(string $productCode): void;

    /**
     * Higher sells better. Only the order of a channel's scores matters
     */
    public function getScore(): float;

    public function setScore(float $score): void;
}
