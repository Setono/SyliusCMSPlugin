<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type;

use Setono\SyliusCMSPlugin\Uploader\AssetUploaderInterface;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Webmozart\Assert\Assert;

final class AssetType extends AbstractResourceType
{
    private AssetUploaderInterface $assetUploader;

    /**
     * @param array<array-key, string> $validationGroups
     */
    public function __construct(AssetUploaderInterface $assetUploader, string $dataClass, array $validationGroups = [])
    {
        parent::__construct($dataClass, $validationGroups);

        $this->assetUploader = $assetUploader;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('path', HiddenType::class)
            ->add('file', FileType::class, [
                'label' => 'setono_sylius_cms.form.asset.file',
                'mapped' => false,
            ])
            // PRE_SUBMIT is where we have the unmapped fields, i.e. the 'file' above
            ->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
                $data = $event->getData();
                Assert::isArray($data);
                Assert::keyExists($data, 'file');

                $file = $data['file'];
                Assert::isInstanceOf($file, UploadedFile::class);

                $data['path'] = $this->assetUploader->uploadFile($file);
                $event->setData($data);
            })
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_cms_asset';
    }
}
