<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type;

use Setono\SyliusCMSPlugin\Model\AssetInterface;
use Setono\SyliusCMSPlugin\Uploader\AssetUploaderInterface;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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
        // todo this is a mess, use the same approach as Sylius does
        $builder
            ->add('path', HiddenType::class)
            ->add('mimeType', HiddenType::class)
            ->add('name', TextType::class, [
                'label' => 'setono_sylius_cms.form.asset.name',
                'required' => false,
            ])
            ->add('code', TextType::class, [
                'label' => 'setono_sylius_cms.form.asset.code',
                'required' => false,
            ])
            ->add('internalDescription', TextareaType::class, [
                'label' => 'setono_sylius_cms.form.internal_description',
                'required' => false,
            ])
            ->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
                /** @var AssetInterface|mixed $asset */
                $asset = $event->getData();
                Assert::isInstanceOf($asset, AssetInterface::class);

                $event->getForm()
                    ->add('file', FileType::class, [
                        'label' => 'setono_sylius_cms.form.asset.file',
                        'mapped' => false,
                        'required' => $asset->getId() === null,
                    ])
                ;
            })
            // PRE_SUBMIT is where we have the unmapped fields, i.e. the 'file' above
            ->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
                $data = $event->getData();
                Assert::isArray($data);
                Assert::keyExists($data, 'file');
                Assert::keyExists($data, 'name');

                /** @var UploadedFile|mixed|null $file */
                $file = $data['file'];
                Assert::nullOrIsInstanceOf($file, UploadedFile::class);

                if (null === $file) {
                    return;
                }

                /** @var string|mixed|null $name */
                $name = $data['name'];
                if (null === $name || '' === $name) {
                    $name = $file->getClientOriginalName();
                }
                Assert::string($name);

                $data['name'] = $name;
                $data['path'] = $this->assetUploader->uploadFile($file);
                $data['mimeType'] = $file->getMimeType();
                $event->setData($data);
            })
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_cms_asset';
    }
}
