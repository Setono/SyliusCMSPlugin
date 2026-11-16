<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

class Homepage implements HomepageInterface
{
    protected ?int $id = null;

    /**
     * @param Page $page the shipped model, which the page resource's model is or extends
     */
    public function __construct(
        protected Page $page,
        protected string $channelCode,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPage(): Page
    {
        return $this->page;
    }

    public function getChannelCode(): string
    {
        return $this->channelCode;
    }
}
