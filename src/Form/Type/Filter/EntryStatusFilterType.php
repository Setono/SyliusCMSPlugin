<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type\Filter;

use Setono\SyliusCMSPlugin\Admin\EntryStatusFilter;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The form of the entry lists' status filter (the grid filter type setono_sylius_cms_entry_status).
 * See "Entry lists" in docs/spec/admin.md
 *
 * @extends AbstractType<EntryStatusFilter|null>
 */
final class EntryStatusFilterType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => EntryStatusFilter::class,
            'choice_label' => static fn (EntryStatusFilter $status): string => $status->label(),
            'placeholder' => 'setono_sylius_cms.ui.any_status',
            'required' => false,
        ]);
    }

    public function getParent(): string
    {
        return EnumType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_cms_entry_status_filter';
    }
}
