<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type\Filter;

use Setono\SyliusCMSPlugin\Localization\ChannelLocales;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * The form of the entry lists' translation filter (the grid filter type setono_sylius_cms_entry_translation): "Not
 * complete in" each locale of the store's channels, named in the admin user's locale, whose values are the locale codes.
 * See "Overview" in docs/spec/admin.md
 *
 * @extends AbstractType<string|null>
 */
final class EntryTranslationFilterType extends AbstractType
{
    /**
     * @param RepositoryInterface<LocaleInterface> $localeRepository
     */
    public function __construct(
        private readonly ChannelLocales $channelLocales,
        private readonly RepositoryInterface $localeRepository,
        private readonly LocaleContextInterface $localeContext,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => fn (Options $options): array => $this->channelLocales->all(),
            'choice_label' => function (Options $options): \Closure {
                $names = $this->names();

                return static fn (string $localeCode): TranslatableMessage => new TranslatableMessage('setono_sylius_cms.ui.not_complete_in', ['%locale%' => $names[$localeCode] ?? $localeCode]);
            },
            'placeholder' => 'setono_sylius_cms.ui.any_translation',
            'required' => false,
        ]);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_cms_entry_translation_filter';
    }

    /**
     * @return array<string, string> the names of the store's locales in the admin user's locale, by code
     */
    private function names(): array
    {
        $displayLocale = $this->localeContext->getLocaleCode();

        $names = [];
        foreach ($this->localeRepository->findAll() as $locale) {
            $code = $locale->getCode();
            if (null !== $code) {
                $names[$code] = $locale->getName($displayLocale) ?? $code;
            }
        }

        return $names;
    }
}
