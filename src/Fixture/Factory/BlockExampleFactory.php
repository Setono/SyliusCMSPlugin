<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Fixture\Factory;

use Faker\Factory;
use Faker\Generator;
use Setono\EditorJS\Parser\ParserInterface;
use Setono\EditorJS\Renderer\RendererInterface;
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

/* not final */ class BlockExampleFactory extends AbstractExampleFactory
{
    protected FactoryInterface $blockFactory;

    protected BlockRepositoryInterface $blockRepository;

    protected RepositoryInterface $localeRepository;

    protected ParserInterface $parser;

    protected RendererInterface $renderer;

    protected Generator $faker;

    protected OptionsResolver $optionsResolver;

    public function __construct(
        FactoryInterface $blockFactory,
        BlockRepositoryInterface $blockRepository,
        RepositoryInterface $localeRepository,
        ParserInterface $parser,
        RendererInterface $renderer
    ) {
        $this->blockFactory = $blockFactory;
        $this->blockRepository = $blockRepository;
        $this->localeRepository = $localeRepository;
        $this->parser = $parser;
        $this->renderer = $renderer;

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

        if (array_key_exists('rawContent', $options)) {
            $rawContent = $options['rawContent'];
            Assert::string($rawContent);

            $block->setDefaultRawContent($rawContent);

            $html = $this->renderer->render(
                $this->parser->parse($rawContent)
            );

            $block->setDefaultContent($html);
        }

        if (array_key_exists('translations', $options)) {
            Assert::isArray($options['translations']);

            foreach ($options['translations'] as $localeCode => $translationOptions) {
                Assert::string($localeCode);
                Assert::isArray($translationOptions);

                $this->createTranslation($block, $localeCode, $translationOptions);
            }
        }

        return $block;
    }

    protected function createTranslation(BlockInterface $block, string $localeCode, array $options = []): void
    {
        $options = $this->optionsResolver->resolve($options);

        /** @var BlockTranslation $translation */
        $translation = $block->getTranslation($localeCode);

        if (array_key_exists('rawContent', $options)) {
            $rawContent = $options['rawContent'];
            Assert::string($rawContent);

            $translation->setRawContent($rawContent);

            $html = $this->renderer->render(
                $this->parser->parse($rawContent)
            );

            $translation->setContent($html);
        }
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        /** @psalm-suppress UnusedClosureParam, MissingClosureParamType */
        $resolver
            ->setDefault('code', function (): string {
                return $this->faker->uuid();
            })
            ->setDefault('rawContent', function (): array {
                return [
                    'time' => 1637858216112,
                    'blocks' => [],
                    'version' => '2.22.2',
                ];
            })
            ->setNormalizer('rawContent', function (Options $options, $rawContent): string {
                if (is_array($rawContent)) {
                    $rawContent = json_encode($rawContent);
                }

                Assert::string($rawContent);

                return $rawContent;
            })
            ->setDefault('translations', [])
            ->setAllowedTypes('translations', ['array'])
        ;
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
