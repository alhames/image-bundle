<?php

namespace Alhames\ImageBundle\DependencyInjection;

use Alhames\ImageBundle\Image;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('alhames_image');

        $treeBuilder->getRootNode()
            ->children()
                ->integerNode('max_size')
                    ->min(1)
                    ->max(PHP_INT_MAX)
                    ->defaultValue(10_000_000)
                ->end()
                ->integerNode('max_width')
                    ->min(1)
                    ->max(PHP_INT_MAX)
                    ->defaultValue(10_000)
                ->end()
                ->integerNode('max_height')
                    ->min(1)
                    ->max(PHP_INT_MAX)
                    ->defaultValue(10_000)
                ->end()
                ->arrayNode('supported_types') // todo: check strings
                    ->scalarPrototype()->end()
                    ->defaultValue([Image::TYPE_JPEG, Image::TYPE_PNG, Image::TYPE_GIF, Image::TYPE_WEBP])
                ->end()
            ->end();

        return $treeBuilder;
    }
}
