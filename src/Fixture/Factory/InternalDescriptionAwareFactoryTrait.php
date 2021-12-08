<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Fixture\Factory;

use Setono\SyliusCMSPlugin\Model\InternalDescriptionAwareInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Webmozart\Assert\Assert;

trait InternalDescriptionAwareFactoryTrait
{
    public function setInternalDescription(InternalDescriptionAwareInterface $descriptionAware, array $options): void
    {
        if (array_key_exists('internalDescription', $options)) {
            $internalDescription = $options['internalDescription'];
            Assert::string($internalDescription);

            $descriptionAware->setInternalDescription($internalDescription);
        }
    }

    protected function configureInternalDescriptionOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefined('internalDescription')
            ->setAllowedTypes('internalDescription', 'string')
        ;
    }
}
