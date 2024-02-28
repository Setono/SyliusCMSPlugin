<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Fixture\Factory;

use Faker\Factory;
use Faker\Generator;
use Setono\SyliusCMSPlugin\Model\AssetInterface;
use Setono\SyliusCMSPlugin\Uploader\AssetUploaderInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\AbstractExampleFactory;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Webmozart\Assert\Assert;

/* not final */ class AssetExampleFactory extends AbstractExampleFactory
{
    use InternalDescriptionAwareFactoryTrait;

    protected FactoryInterface $assetFactory;

    protected FileLocatorInterface $fileLocator;

    protected AssetUploaderInterface $assetUploader;

    protected Generator $faker;

    protected OptionsResolver $optionsResolver;

    public function __construct(
        FactoryInterface $assetFactory,
        FileLocatorInterface $fileLocator,
        AssetUploaderInterface $assetUploader,
    ) {
        $this->assetFactory = $assetFactory;
        $this->fileLocator = $fileLocator;
        $this->assetUploader = $assetUploader;

        $this->faker = Factory::create();
        $this->optionsResolver = new OptionsResolver();

        $this->configureOptions($this->optionsResolver);
    }

    public function create(array $options = []): AssetInterface
    {
        $options = $this->optionsResolver->resolve($options);

        Assert::keyExists($options, 'path');
        Assert::string($options['path']);

        $assetPath = $this->fileLocator->locate($options['path']);
        $uploadedFile = new UploadedFile($assetPath, basename($assetPath));
        $uploadedFilePath = $this->assetUploader->uploadFile($uploadedFile);

        /** @var AssetInterface $asset */
        $asset = $this->assetFactory->createNew();
        $asset->setPath($uploadedFilePath);
        $asset->setMimeType($uploadedFile->getMimeType());

        if (array_key_exists('name', $options)) {
            $name = $options['name'];
            Assert::string($name);

            $asset->setName($name);
        } else {
            $asset->setName($uploadedFile->getClientOriginalName());
        }

        $this->setInternalDescription($asset, $options);

        return $asset;
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefined('name')
            ->setAllowedTypes('name', 'string')

            ->setRequired('path')
            ->setAllowedTypes('path', 'string')
        ;

        $this->configureInternalDescriptionOptions($resolver);
    }
}
