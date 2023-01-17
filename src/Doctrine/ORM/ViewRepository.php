<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Doctrine\ORM;

use Setono\SyliusCMSPlugin\Model\TemplateInterface;
use Setono\SyliusCMSPlugin\Model\ViewInterface;
use Setono\SyliusCMSPlugin\Repository\ViewRepositoryInterface;
use Webmozart\Assert\Assert;

class ViewRepository extends ElementRepository implements ViewRepositoryInterface
{
    public function findOneByCode(string $code): ?ViewInterface
    {
        $obj = parent::findOneByCode($code);

        Assert::nullOrIsInstanceOf($obj, ViewInterface::class);

        return $obj;
    }

    public function findByTemplate(TemplateInterface $template): array
    {
        $code = $template->getCode();
        Assert::notNull($code);

        /** @var ViewInterface[] $views */
        $views = $this->findBy([
            'template' => $code,
        ]);

        Assert::allIsInstanceOf($views, ViewInterface::class);

        return $views;
    }
}
