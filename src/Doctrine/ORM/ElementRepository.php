<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Doctrine\ORM;

use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Webmozart\Assert\Assert;

class ElementRepository extends EntityRepository
{
    public function findOneByCode(string $code): ?ElementInterface
    {
        $obj = $this->findOneBy([
            'code' => $code,
        ]);

        Assert::nullOrIsInstanceOf($obj, ElementInterface::class);

        return $obj;
    }
}
