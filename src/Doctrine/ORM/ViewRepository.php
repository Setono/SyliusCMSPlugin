<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Doctrine\ORM;

use Setono\SyliusCMSPlugin\Model\TemplateInterface;
use Setono\SyliusCMSPlugin\Model\ViewInterface;
use Setono\SyliusCMSPlugin\Repository\ViewRepositoryInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Webmozart\Assert\Assert;

class ViewRepository extends EntityRepository implements ViewRepositoryInterface
{
    public function findOneByCode(string $code): ?ViewInterface
    {
        $view = $this->findOneBy([
            'code' => $code,
        ]);

        Assert::nullOrIsInstanceOf($view, ViewInterface::class);

        return $view;
    }

    public function findByTemplate(TemplateInterface $template): array
    {
        $code = $template->getCode();
        Assert::notNull($code);

        $views = $this->findBy([
            'template' => $code,
        ]);

        Assert::allIsInstanceOf($views, ViewInterface::class);

        return $views;
    }
}
