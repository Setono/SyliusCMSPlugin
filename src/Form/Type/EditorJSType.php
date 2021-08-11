<?php
declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

final class EditorJSType extends AbstractType
{
    public function getParent(): string
    {
        return TextareaType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_cms_editorjs';
    }
}
