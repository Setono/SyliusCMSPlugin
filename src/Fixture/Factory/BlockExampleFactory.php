<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Fixture\Factory;

use Faker\Factory;
use Faker\Generator;
use Setono\SyliusCMSPlugin\Model\BlockInterface;
use Setono\SyliusCMSPlugin\Model\BlockTranslation;
use Setono\SyliusCMSPlugin\Repository\BlockRepositoryInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\AbstractExampleFactory;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Webmozart\Assert\Assert;

class BlockExampleFactory extends AbstractExampleFactory
{
    use InternalDescriptionAwareFactoryTrait;

    protected Generator $faker;

    protected OptionsResolver $optionsResolver;

    public function __construct(
        protected readonly FactoryInterface $blockFactory,
        protected readonly BlockRepositoryInterface $blockRepository,
        protected readonly RepositoryInterface $localeRepository,
    ) {
        $this->faker = Factory::create();
        $this->optionsResolver = new OptionsResolver();

        $this->configureOptions($this->optionsResolver);
    }

    public function create(array $options = []): BlockInterface
    {
        $options = $this->optionsResolver->resolve($options);

        Assert::keyExists($options, 'code');
        Assert::string($options['code']);
        $code = $options['code'];

        /** @var BlockInterface|null $block */
        $block = $this->blockRepository->findOneBy(['code' => $code]);
        if (null === $block) {
            /** @var BlockInterface $block */
            $block = $this->blockFactory->createNew();
            $block->setCode($code);
        }

        if (array_key_exists('content', $options)) {
            $content = $options['content'];
            Assert::string($content);

            $block->setDefaultContent($content);
        }

        if (array_key_exists('translations', $options)) {
            Assert::isArray($options['translations']);

            foreach ($options['translations'] as $localeCode => $translationOptions) {
                Assert::string($localeCode);
                Assert::isArray($translationOptions);

                $this->createTranslation($block, $localeCode, $translationOptions);
            }
        }

        $this->setInternalDescription($block, $options);

        return $block;
    }

    protected function createTranslation(BlockInterface $block, string $localeCode, array $options = []): void
    {
        $options = $this->optionsResolver->resolve($options);

        /** @var BlockTranslation $translation */
        $translation = $block->getTranslation($localeCode);

        if (array_key_exists('content', $options)) {
            $content = $options['content'];
            Assert::string($content);

            $translation->setContent($content);
        }
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        /** @psalm-suppress UnusedClosureParam, MissingClosureParamType */
        $resolver
            ->setDefault('code', fn (): string => $this->faker->uuid())
            ->setDefault('content', fn (): array => [
                'time' => 1637858216112,
                'blocks' => [],
                'version' => '2.22.2',
            ])
            ->setNormalizer('content', function (Options $options, $content): string {
                if (is_array($content)) {
                    $content = json_encode($content);
                }

                Assert::string($content);

                return $content;
            })
            ->setDefault('translations', [])
            ->setAllowedTypes('translations', ['array'])
        ;

        $this->configureInternalDescriptionOptions($resolver);
    }

    protected function getLocales(): iterable
    {
        /** @var LocaleInterface[] $locales */
        $locales = $this->localeRepository->findAll();
        foreach ($locales as $locale) {
            yield $locale->getCode();
        }
    }
}
