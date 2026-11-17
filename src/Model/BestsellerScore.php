<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

class BestsellerScore implements BestsellerScoreInterface
{
    protected ?int $id = null;

    protected string $channelCode = '';

    protected string $productCode = '';

    protected float $score = 0.0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChannelCode(): string
    {
        return $this->channelCode;
    }

    public function setChannelCode(string $channelCode): void
    {
        $this->channelCode = $channelCode;
    }

    public function getProductCode(): string
    {
        return $this->productCode;
    }

    public function setProductCode(string $productCode): void
    {
        $this->productCode = $productCode;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function setScore(float $score): void
    {
        $this->score = $score;
    }
}
