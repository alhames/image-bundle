<?php

namespace Alhames\ImageBundle\DependencyInjection;

use Alhames\ImageBundle\ImageManager;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class AlhamesImageExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $definition = new Definition(ImageManager::class);
        $definition->setAutowired(true);
        $definition = $definition->setArgument('$options', $config);
        $container->setDefinition(ImageManager::class, $definition);
    }
}
