<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type;

use Setono\SyliusCMSPlugin\Controller\Admin\PresetsScreenRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * An editor preset's Delete button on the Presets screen: a form of no fields but its CSRF token, named after the
 * preset (PresetsScreenRequest::formName()). See "Presets" in docs/spec/admin.md
 *
 * @extends AbstractType<null>
 */
final class DeletePresetType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => PresetsScreenRequest::CSRF_TOKEN_ID,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_cms_delete_preset';
    }
}
