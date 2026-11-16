<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type;

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
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * The create dialog's form: the title, the slug for a content type with its own URL, and the channels, all of the
 * store's picked to start with, in a store with more than one. A slug left empty is generated from the title with
 * Sylius's slug generator, as the dialog does while the editor types the title. See "Create dialog" in docs/spec/admin.md
 *
 * @extends AbstractType<array{title: string, slug?: string, channels?: array<int, string>}>
 */
final class CreateEntryType extends AbstractType
{
    /**
     * A slug can't be empty, start or end with a slash, or contain two slashes in a row
     */
    public const SLUG_PATTERN = '#^(?!/)(?!.*//)(?!.*/$).+$#';

    /**
     * SLUG_PATTERN as an HTML pattern attribute, which the browser anchors itself
     */
    private const SLUG_HTML_PATTERN = '(?!/)(?!.*//)(?!.*/$).+';

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
                'attr' => ['maxlength' => self::MAX_LENGTH, 'pattern' => self::SLUG_HTML_PATTERN, 'title' => 'setono_sylius_cms.form.entry.slug_rules'],
                'constraints' => [
                    new NotBlank(),
                    new Length(max: self::MAX_LENGTH),
                    new Regex(pattern: self::SLUG_PATTERN, message: 'setono_sylius_cms.slug.invalid'),
                ],
            ]);

            $builder->addEventListener(FormEvents::PRE_SUBMIT, $this->fillSlug(...));
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
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_cms_create_entry';
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
