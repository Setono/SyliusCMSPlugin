<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type;

use Setono\SyliusCMSPlugin\Admin\StartingPointChoice;
use Setono\SyliusCMSPlugin\Routing\Slug;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Product\Generator\SlugGeneratorInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * The create dialog's form: the title, the slug for a content type with its own URL, the layout, from wireframe cards,
 * for a content type with more than one, the starting point, Blank or one of the content type's, when it has any, and
 * the channels, all of the store's picked to start with, in a store with more than one. A slug left empty is generated
 * from the title with Sylius's slug generator, as the dialog does while the editor types the title. See "Create
 * dialog" in docs/spec/admin.md
 *
 * @extends AbstractType<array{title: string, slug?: string, layout?: string, startingPoint?: string|null, channels?: array<int, string>}>
 */
final class CreateEntryType extends AbstractType
{
    private const MAX_LENGTH = 255;

    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        private readonly SlugGeneratorInterface $slugGenerator,
        private readonly ChannelRepositoryInterface $channelRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('title', TextType::class, [
            'label' => 'setono_sylius_cms.form.entry.title',
            'attr' => ['maxlength' => self::MAX_LENGTH],
            'constraints' => [new NotBlank(), new Length(max: self::MAX_LENGTH)],
        ]);

        if (true === $options['slug']) {
            $builder->add('slug', TextType::class, [
                'label' => 'setono_sylius_cms.form.entry.slug',
                'help' => 'setono_sylius_cms.form.entry.slug_help',
                // An empty slug is generated from the title
                'required' => false,
                'empty_data' => '',
                'attr' => ['maxlength' => self::MAX_LENGTH, 'pattern' => Slug::HTML_PATTERN, 'title' => 'setono_sylius_cms.form.entry.slug_rules'],
                'constraints' => [
                    new NotBlank(),
                    new Length(max: self::MAX_LENGTH),
                    new Regex(pattern: Slug::PATTERN, message: 'setono_sylius_cms.slug.invalid'),
                ],
            ]);

            $builder->addEventListener(FormEvents::PRE_SUBMIT, $this->fillSlug(...));
        }

        /** @var array<string, array{label: string, thumbnail: string|null}> $layouts */
        $layouts = $options['layouts'];
        if (count($layouts) > 1) {
            $builder->add('layout', ChoiceType::class, [
                'label' => 'setono_sylius_cms.form.entry.layout',
                'choices' => array_keys($layouts),
                'choice_label' => static fn (string $code): string => $layouts[$code]['label'],
                // The card's wireframe
                'choice_attr' => static fn (string $code): array => null === $layouts[$code]['thumbnail'] ? [] : ['data-thumbnail' => $layouts[$code]['thumbnail']],
                'expanded' => true,
                'multiple' => false,
                'data' => array_key_first($layouts),
            ]);
        }

        /** @var list<StartingPointChoice> $startingPoints */
        $startingPoints = $options['starting_points'];
        if ([] !== $startingPoints) {
            $this->addStartingPoint($builder, $startingPoints);
        }

        $channels = $this->channels();
        if (count($channels) > 1) {
            $builder->add('channels', ChoiceType::class, [
                'label' => 'setono_sylius_cms.form.entry.channels',
                'choices' => $channels,
                'choice_translation_domain' => false,
                'expanded' => true,
                'multiple' => true,
                'data' => array_values($channels),
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('slug', true)
            ->setAllowedTypes('slug', 'bool')
            ->setInfo('slug', 'Whether the content type has its own URL, and so a slug')
            ->setDefault('layouts', [])
            ->setAllowedTypes('layouts', 'array')
            ->setInfo('layouts', 'The layouts editors can pick, by code, each with its label and thumbnail: the default first, which is picked to start with')
            ->setDefault('starting_points', [])
            ->setAllowedTypes('starting_points', StartingPointChoice::class . '[]')
            ->setInfo('starting_points', 'The content type\'s starting points, which editors can pick instead of Blank')
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_cms_create_entry';
    }

    /**
     * The starting point, from cards: Blank, the field's placeholder, which leaves the tree as the content type starts
     * it, then the content type's starting points. The default is picked to start with, unless it's disabled, and
     * otherwise Blank. A disabled one is shown but refused
     *
     * @param FormBuilderInterface<array{title: string, slug?: string, layout?: string, startingPoint?: string|null, channels?: array<int, string>}|null> $builder
     * @param list<StartingPointChoice> $startingPoints
     */
    private function addStartingPoint(FormBuilderInterface $builder, array $startingPoints): void
    {
        $byId = [];
        foreach ($startingPoints as $startingPoint) {
            $byId[$startingPoint->id()] = $startingPoint;
        }

        $default = null;
        foreach ($startingPoints as $startingPoint) {
            if ($startingPoint->default && !$startingPoint->disabled) {
                $default = $startingPoint->id();

                break;
            }
        }

        $enabled = array_keys(array_filter($byId, static fn (StartingPointChoice $startingPoint): bool => !$startingPoint->disabled));

        $builder->add('startingPoint', ChoiceType::class, [
            'label' => 'setono_sylius_cms.form.entry.starting_point',
            'help' => 'setono_sylius_cms.form.entry.starting_point_help',
            'choices' => array_keys($byId),
            'choice_label' => static fn (string $id): string => $byId[$id]->name,
            'choice_translation_domain' => false,
            'choice_attr' => static fn (string $id): array => self::startingPointAttributes($byId[$id]),
            'expanded' => true,
            'multiple' => false,
            'required' => false,
            'placeholder' => 'setono_sylius_cms.form.entry.blank',
            'data' => $default,
            'constraints' => [new Choice(choices: $enabled, message: 'setono_sylius_cms.starting_point.disabled')],
        ]);
    }

    /**
     * The card's thumbnail and description, and whether it's disabled
     *
     * @return array<string, string>
     */
    private static function startingPointAttributes(StartingPointChoice $startingPoint): array
    {
        $attributes = [];
        if (null !== $startingPoint->thumbnail) {
            $attributes['data-thumbnail'] = $startingPoint->thumbnail;
        }

        if (null !== $startingPoint->description) {
            $attributes['data-description'] = $startingPoint->description;
        }

        if ($startingPoint->disabled) {
            $attributes['disabled'] = 'disabled';
        }

        return $attributes;
    }

    /**
     * Generates the slug from the title when the editor left the slug empty
     */
    private function fillSlug(FormEvent $event): void
    {
        $data = $event->getData();
        if (!is_array($data)) {
            return;
        }

        $title = $data['title'] ?? null;
        $slug = $data['slug'] ?? null;
        if (is_string($title) && (null === $slug || (is_string($slug) && '' === trim($slug)))) {
            $data['slug'] = $this->slugGenerator->generate($title);
            $event->setData($data);
        }
    }

    /**
     * @return array<string, string> the channels' names, or else their codes => their codes, by name
     */
    private function channels(): array
    {
        $channels = [];
        foreach ($this->channelRepository->findBy([], ['name' => 'ASC']) as $channel) {
            $code = $channel->getCode();
            if (null === $code) {
                continue;
            }

            $channels[$channel->getName() ?? $code] = $code;
        }

        return $channels;
    }
}
