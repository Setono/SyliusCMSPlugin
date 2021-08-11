<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type;

use Setono\EditorJS\Parser\ParserInterface;
use Setono\EditorJS\Renderer\RendererInterface;
use Setono\SyliusCMSPlugin\Form\EventSubscriber\ConvertRawContentSubscriber;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Sylius\Bundle\ResourceBundle\Form\Type\ResourceTranslationsType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class BlockType extends AbstractResourceType
{
    private ParserInterface $parser;

    private RendererInterface $renderer;

    /**
     * @param array<array-key, string> $validationGroups
     */
    public function __construct(
        ParserInterface $parser,
        RendererInterface $renderer,
        string $dataClass,
        array $validationGroups = []
    ) {
        parent::__construct($dataClass, $validationGroups);

        $this->parser = $parser;
        $this->renderer = $renderer;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'setono_sylius_cms.form.block.code',
            ])
            ->add('defaultContent', HiddenType::class)
            ->add('defaultRawContent', EditorJSType::class, [
                'label' => 'setono_sylius_cms.form.block.default_content',
            ])
            ->add('translations', ResourceTranslationsType::class, [
                'entry_type' => BlockTranslationType::class,
                'label' => 'setono_sylius_cms.form.block.translations',
            ])
            ->addEventSubscriber(new ConvertRawContentSubscriber(
                $this->parser,
                $this->renderer,
                'defaultRawContent',
                'defaultContent'
            ));
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_cms_block';
    }
}
