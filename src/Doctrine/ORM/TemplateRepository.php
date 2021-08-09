<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Doctrine\ORM;

use Setono\SyliusCMSPlugin\Model\TemplateInterface;
use Setono\SyliusCMSPlugin\Repository\TemplateRepositoryInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Webmozart\Assert\Assert;

class TemplateRepository extends EntityRepository implements TemplateRepositoryInterface
{
    public function findOneByCode(string $code): ?TemplateInterface
    {
        $obj = $this->findOneBy([
            'code' => $code
        ]);

        Assert::nullOrIsInstanceOf($obj, TemplateInterface::class);

        return $obj;
    }
}
