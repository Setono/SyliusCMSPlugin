<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Fixture\Factory;

use Faker\Factory;
use Faker\Generator;
use Setono\SyliusCMSPlugin\Model\BlockInterface;
use Setono\SyliusCMSPlugin\Model\CarouselBlockInterface;
use Setono\SyliusCMSPlugin\Model\CarouselInterface;
use Setono\SyliusCMSPlugin\Repository\BlockRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\CarouselRepositoryInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\AbstractExampleFactory;
use Sylius\Bundle\CoreBundle\Fixture\OptionsResolver\LazyOption;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Webmozart\Assert\Assert;

/* not final */ class CarouselExampleFactory extends AbstractExampleFactory
{
    protected FactoryInterface $carouselFactory;

    protected CarouselRepositoryInterface $carouselRepository;

    protected BlockRepositoryInterface $blockRepository;

    protected FactoryInterface $carouselBlockFactory;

    protected Generator $faker;

    protected OptionsResolver $optionsResolver;

    public function __construct(
        FactoryInterface $carouselFactory,
        CarouselRepositoryInterface $carouselRepository,
        BlockRepositoryInterface $blockRepository,
        FactoryInterface $carouselBlockFactory
    ) {
        $this->carouselFactory = $carouselFactory;
        $this->carouselRepository = $carouselRepository;
        $this->blockRepository = $blockRepository;
        $this->carouselBlockFactory = $carouselBlockFactory;

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

        if (array_key_exists('carouselBlocks', $options)) {
            $blocks = $options['carouselBlocks'];
            Assert::isArray($blocks);
            Assert::allIsInstanceOf($blocks, BlockInterface::class);

            foreach ($blocks as $position => $block) {
                Assert::integer($position);

                /** @var CarouselBlockInterface $carouselBlock */
                $carouselBlock = $this->carouselBlockFactory->createNew();
                $carouselBlock->setBlock($block);
                $carouselBlock->setPosition($position);

                $carousel->addCarouselBlock($carouselBlock);
            }
        }

        return $carousel;
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('code', function (): string {
                return $this->faker->uuid();
            })
            ->setAllowedTypes('code', 'string')

            ->setDefined('configuration')
            ->setAllowedTypes('configuration', 'array')

            ->setDefined('carouselBlocks')
            ->setAllowedTypes('carouselBlocks', 'array')
            ->setNormalizer('carouselBlocks', LazyOption::findBy($this->blockRepository, 'code'))
        ;
    }
}
