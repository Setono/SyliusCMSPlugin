<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Fixture\Factory;

use Faker\Factory;
use Faker\Generator;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Model\ViewInterface;
use Setono\SyliusCMSPlugin\Repository\PageRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\ViewRepositoryInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\AbstractExampleFactory;
use Sylius\Bundle\CoreBundle\Fixture\OptionsResolver\LazyOption;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Webmozart\Assert\Assert;

/* not final */ class PageExampleFactory extends AbstractExampleFactory
{
    use InternalDescriptionAwareFactoryTrait;

    protected FactoryInterface $pageFactory;

    protected PageRepositoryInterface $pageRepository;

    protected ViewRepositoryInterface $viewRepository;

    protected ChannelRepositoryInterface $channelRepository;

    protected RepositoryInterface $localeRepository;

    protected Generator $faker;

    protected OptionsResolver $optionsResolver;

    public function __construct(
        FactoryInterface $pageFactory,
        PageRepositoryInterface $pageRepository,
        ViewRepositoryInterface $viewRepository,
        ChannelRepositoryInterface $channelRepository,
        RepositoryInterface $localeRepository
    ) {
        $this->pageFactory = $pageFactory;
        $this->pageRepository = $pageRepository;
        $this->viewRepository = $viewRepository;
        $this->channelRepository = $channelRepository;
        $this->localeRepository = $localeRepository;

        $this->faker = Factory::create();
        $this->optionsResolver = new OptionsResolver();

        $this->configureOptions($this->optionsResolver);
    }

    public function create(array $options = []): PageInterface
    {
        $options = $this->optionsResolver->resolve($options);

        Assert::keyExists($options, 'code');
        Assert::string($options['code']);
        $code = $options['code'];

        /** @var PageInterface|null $page */
        $page = $this->pageRepository->findOneBy(['code' => $code]);
        if (null === $page) {
            /** @var PageInterface $page */
            $page = $this->pageFactory->createNew();
            $page->setCode($code);
        }

        if (array_key_exists('view', $options)) {
            $view = $options['view'];
            Assert::isInstanceOf($view, ViewInterface::class);
            $page->setView($view);
        }

        if (array_key_exists('channels', $options)) {
            $channels = $options['channels'];
            Assert::allIsInstanceOf($channels, ChannelInterface::class);

            foreach ($channels as $channel) {
                $page->addChannel($channel);
            }
        }

        // add translation for each defined locales
        foreach ($this->getLocales() as $localeCode) {
            Assert::string($localeCode);

            $this->createTranslation($page, $localeCode, $options);
        }

        if (array_key_exists('translations', $options)) {
            $translations = $options['translations'];
            Assert::isArray($translations);

            foreach ($translations as $localeCode => $translationOptions) {
                Assert::string($localeCode);
                Assert::isArray($translationOptions);

                $this->createTranslation($page, $localeCode, $translationOptions);
            }
        }

        $this->setInternalDescription($page, $options);

        return $page;
    }

    protected function createTranslation(PageInterface $page, string $localeCode, array $options = []): void
    {
        $options = $this->optionsResolver->resolve($options);

        $page->setCurrentLocale($localeCode);
        $page->setFallbackLocale($localeCode);

        if (array_key_exists('title', $options)) {
            $title = $options['title'];
            Assert::string($title);
            $page->setTitle($title);
        }

        if (array_key_exists('slug', $options)) {
            $slug = $options['slug'];
            Assert::string($slug);
            $page->setSlug($slug);
        }

        if (array_key_exists('metaDescription', $options)) {
            $metaDescription = $options['metaDescription'];
            Assert::string($metaDescription);
            $page->setMetaDescription($metaDescription);
        }
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('code', function (): string {
                return $this->faker->uuid3();
            })

            ->setDefined('view')
            ->setAllowedTypes('view', ['string', ViewInterface::class])
            ->setNormalizer('view', LazyOption::findOneBy($this->viewRepository, 'code'))

            ->setDefault('title', function (): string {
                $title = $this->faker->words(4, true);
                Assert::string($title);

                return $title;
            })

            ->setDefault('slug', function (): string {
                return $this->faker->uuid3();
            })

            ->setDefault('metaDescription', function (): string {
                return $this->faker->paragraph(1);
            })

            ->setDefault('translations', [])
            ->setAllowedTypes('translations', ['array'])

            ->setDefault('channels', LazyOption::all($this->channelRepository))
            ->setAllowedTypes('channels', 'array')
            ->setNormalizer('channels', LazyOption::findBy($this->channelRepository, 'code'))
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
