<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Fixture\Factory;

use Faker\Factory;
use Faker\Generator;
use Setono\SyliusCMSPlugin\Model\BlockInterface;
use Setono\SyliusCMSPlugin\Model\ViewBlockInterface;
use Setono\SyliusCMSPlugin\Model\ViewInterface;
use Setono\SyliusCMSPlugin\Repository\BlockRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\ViewRepositoryInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\AbstractExampleFactory;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Webmozart\Assert\Assert;

/* not final */ class ViewExampleFactory extends AbstractExampleFactory
{
    use InternalDescriptionAwareFactoryTrait;

    protected FactoryInterface $viewFactory;

    protected ViewRepositoryInterface $viewRepository;

    protected BlockRepositoryInterface $blockRepository;

    protected FactoryInterface $viewBlockFactory;

    protected Generator $faker;

    protected OptionsResolver $optionsResolver;

    public function __construct(
        FactoryInterface $viewFactory,
        ViewRepositoryInterface $viewRepository,
        BlockRepositoryInterface $blockRepository,
        FactoryInterface $viewBlockFactory,
    ) {
        $this->viewFactory = $viewFactory;
        $this->viewRepository = $viewRepository;
        $this->blockRepository = $blockRepository;
        $this->viewBlockFactory = $viewBlockFactory;

        $this->faker = Factory::create();
        $this->optionsResolver = new OptionsResolver();

        $this->configureOptions($this->optionsResolver);
    }

    public function create(array $options = []): ViewInterface
    {
        $options = $this->optionsResolver->resolve($options);

        Assert::keyExists($options, 'code');
        Assert::string($options['code']);
        $code = $options['code'];

        /** @var ViewInterface|null $view */
        $view = $this->viewRepository->findOneBy(['code' => $code]);
        if (null === $view) {
            /** @var ViewInterface $view */
            $view = $this->viewFactory->createNew();
            $view->setCode($code);
        }

        if (array_key_exists('template', $options)) {
            $template = $options['template'];
            Assert::string($template);
            $view->setTemplate($template);
        }

        if (array_key_exists('viewBlocks', $options)) {
            $sectionedViewBlocks = $options['viewBlocks'];
            Assert::isArray($sectionedViewBlocks);

            foreach ($sectionedViewBlocks as $section => $blocks) {
                Assert::string($section);
                Assert::isArray($blocks);
                Assert::allIsInstanceOf($blocks, BlockInterface::class);

                foreach ($blocks as $position => $block) {
                    Assert::integer($position);

                    /** @var ViewBlockInterface $viewBlock */
                    $viewBlock = $this->viewBlockFactory->createNew();
                    $viewBlock->setSection($section);
                    $viewBlock->setBlock($block);
                    $viewBlock->setPosition($position);

                    $view->addViewBlock($viewBlock);
                }
            }
        }

        $this->setInternalDescription($view, $options);

        return $view;
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        /** @psalm-suppress UnusedClosureParam, MissingClosureParamType */
        $resolver
            ->setDefault('code', function (): string {
                return $this->faker->uuid();
            })
            ->setAllowedTypes('code', 'string')

            ->setDefined('template')
            ->setAllowedTypes('template', 'string')

            ->setDefined('viewBlocks')
            ->setNormalizer('viewBlocks', function (Options $options, array $sectionedViewBlocks): array {
                return $this->normalizeViewBlocks($sectionedViewBlocks);
            })
        ;

        $this->configureInternalDescriptionOptions($resolver);
    }

    protected function normalizeViewBlocks(array $sectionedViewBlocks): array
    {
        foreach ($sectionedViewBlocks as $section => $viewBlocks) {
            Assert::string($section);
            Assert::isArray($viewBlocks);
            Assert::allString($viewBlocks);

            foreach ($viewBlocks as $position => $blockCode) {
                $block = $this->blockRepository->findOneByCode($blockCode);
                Assert::notNull($block, sprintf('Block %s does not exists', $blockCode));

                /** @psalm-suppress MixedArrayAssignment */
                $sectionedViewBlocks[$section][$position] = $block;
            }
        }

        return $sectionedViewBlocks;
    }
}
