<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type;

use Setono\EditorJS\Parser\ParserInterface;
use Setono\EditorJS\Renderer\RendererInterface;
use Setono\SyliusCMSPlugin\Form\EventSubscriber\ConvertRawContentSubscriber;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;

final class BlockTranslationType extends AbstractResourceType
{
    /**
     * @param array<array-key, string> $validationGroups
     */
    public function __construct(
        private readonly ParserInterface $parser,
        private readonly RendererInterface $renderer,
        string $dataClass,
        array $validationGroups = [],
    ) {
        parent::__construct($dataClass, $validationGroups);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', HiddenType::class)
            ->add('rawContent', EditorJSType::class, [
                'label' => 'setono_sylius_cms.form.block.content',
            ])
            ->addEventSubscriber(new ConvertRawContentSubscriber($this->parser, $this->renderer));
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_cms_block_translation';
    }
}
