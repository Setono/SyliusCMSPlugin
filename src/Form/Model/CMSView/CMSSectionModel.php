<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Model\CMSView;

/** @internal */
class CMSSectionModel
{
    public string $name = '';

    /** @var array<int, CMSBlockModel> */
    public array $blocks = [];
}
