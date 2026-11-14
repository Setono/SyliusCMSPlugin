<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * The minimal form that creates an entry: a title, and a slug for a content type with its own URL.
 * The create dialog (#230) replaces it
 *
 * @extends AbstractType<array{title: string, slug?: string}>
 */
final class CreateEntryType extends AbstractType
{
    /**
     * A slug can't be empty, start or end with a slash, or contain two slashes in a row
     */
    public const SLUG_PATTERN = '#^(?!/)(?!.*//)(?!.*/$).+$#';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('title', TextType::class, [
            'label' => 'setono_sylius_cms.form.entry.title',
            'constraints' => [new NotBlank(), new Length(max: 255)],
        ]);

        if (true === $options['slug']) {
            $builder->add('slug', TextType::class, [
                'label' => 'setono_sylius_cms.form.entry.slug',
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 255),
                    new Regex(pattern: self::SLUG_PATTERN, message: 'setono_sylius_cms.slug.invalid'),
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('slug', true)
            ->setAllowedTypes('slug', 'bool')
            ->setInfo('slug', 'Whether the content type has its own URL, and so a slug')
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_cms_create_entry';
    }
}
