<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection\Compiler;

use Setono\SyliusCMSPlugin\Template\Template;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Webmozart\Assert\Assert;

final class RegisterTemplatesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('setono_sylius_cms.template.registry')) {
            return;
        }

        $registry = $container->getDefinition('setono_sylius_cms.template.registry');

        $templates = $container->getParameter('setono_sylius_cms.templates');
        Assert::isArray($templates);

        /**
         * @var string $key
         * @var array<string, string> $template
         */
        foreach ($templates as $key => $template) {
            $templateId = 'setono_sylius_cms.template.' . $key;
            $container->set($templateId, new Template($key, $template['path'], $template['label']));
            $registry->addMethodCall('add', [new Reference($templateId)]);
        }
    }
}
