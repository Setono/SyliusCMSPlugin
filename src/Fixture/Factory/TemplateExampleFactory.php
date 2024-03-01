<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Fixture\Factory;

use Faker\Factory;
use Faker\Generator;
use Setono\SyliusCMSPlugin\Model\TemplateInterface;
use Setono\SyliusCMSPlugin\Repository\TemplateRepositoryInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\AbstractExampleFactory;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Webmozart\Assert\Assert;

class TemplateExampleFactory extends AbstractExampleFactory
{
    use InternalDescriptionAwareFactoryTrait;

    protected Generator $faker;

    protected OptionsResolver $optionsResolver;

    public function __construct(
        protected readonly FactoryInterface $templateFactory,
        protected readonly TemplateRepositoryInterface $templateRepository,
    ) {
        $this->faker = Factory::create();
        $this->optionsResolver = new OptionsResolver();

        $this->configureOptions($this->optionsResolver);
    }

    public function create(array $options = []): TemplateInterface
    {
        $options = $this->optionsResolver->resolve($options);

        Assert::keyExists($options, 'code');
        Assert::string($options['code']);
        $code = $options['code'];

        /** @var TemplateInterface|null $template */
        $template = $this->templateRepository->findOneBy(['code' => $code]);
        if (null === $template) {
            /** @var TemplateInterface $template */
            $template = $this->templateFactory->createNew();
            $template->setCode($code);
        }

        Assert::keyExists($options, 'source');
        Assert::string($options['source']);
        $template->setSource($options['source']);

        $this->setInternalDescription($template, $options);

        return $template;
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('code', fn (): string => $this->faker->uuid())
            ->setDefault('source', fn (): string => '{% sscms_section content %}')
        ;

        $this->configureInternalDescriptionOptions($resolver);
    }
}
