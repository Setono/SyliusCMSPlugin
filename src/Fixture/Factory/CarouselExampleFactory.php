<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Fixture\Factory;

use Faker\Factory;
use Faker\Generator;
use Setono\SyliusCMSPlugin\Model\CarouselInterface;
use Setono\SyliusCMSPlugin\Repository\BlockRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\CarouselRepositoryInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\AbstractExampleFactory;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Webmozart\Assert\Assert;

class CarouselExampleFactory extends AbstractExampleFactory
{
    use InternalDescriptionAwareFactoryTrait;

    protected Generator $faker;

    protected OptionsResolver $optionsResolver;

    public function __construct(
        protected readonly FactoryInterface $carouselFactory,
        protected readonly CarouselRepositoryInterface $carouselRepository,
        protected readonly BlockRepositoryInterface $blockRepository,
    ) {
        $this->faker = Factory::create();
        $this->optionsResolver = new OptionsResolver();

        $this->configureOptions($this->optionsResolver);
    }

    public function create(array $options = []): CarouselInterface
    {
        $options = $this->optionsResolver->resolve($options);

        Assert::keyExists($options, 'code');
        Assert::string($options['code']);
        $code = $options['code'];

        /** @var CarouselInterface|null $carousel */
        $carousel = $this->carouselRepository->findOneBy(['code' => $code]);
        if (null === $carousel) {
            /** @var CarouselInterface $carousel */
            $carousel = $this->carouselFactory->createNew();
            $carousel->setCode($code);
        }

        if (array_key_exists('configuration', $options)) {
            $configuration = $options['configuration'];
            Assert::isArray($configuration);
            Assert::allString(array_keys($configuration));
            $carousel->setConfiguration($configuration);
        }

        $this->setInternalDescription($carousel, $options);

        return $carousel;
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('code', fn (): string => $this->faker->uuid())
            ->setAllowedTypes('code', 'string')

            ->setDefined('configuration')
            ->setAllowedTypes('configuration', 'array')
        ;

        $this->configureInternalDescriptionOptions($resolver);
    }
}
