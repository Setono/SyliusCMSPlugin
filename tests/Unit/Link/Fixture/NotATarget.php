<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Link\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsLinkTarget;

#[AsLinkTarget(code: 'app_not_a_target', label: 'app.link_target.not_a_target')]
final class NotATarget
{
}
