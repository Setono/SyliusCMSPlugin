<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

interface RenderContextFactoryInterface
{
    /**
     * @param array<string, object> $hostPageContext context key => object
     */
    public function create(array $hostPageContext = []): RenderContext;
}
