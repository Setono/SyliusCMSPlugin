<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

class Homepage implements HomepageInterface
{
    protected ?int $id = null;

    public function __construct(
        protected PageInterface $page,
        protected string $channelCode,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPage(): PageInterface
    {
        return $this->page;
    }

    public function getChannelCode(): string
    {
        return $this->channelCode;
    }
}
