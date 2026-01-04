<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('progressive_image');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->beforeNormalization()
                ->ifArray()
                ->then(function ($v) {
                    if (isset($v['resolver']) && !isset($v['resolvers'][$v['resolver']]) && !in_array($v['resolver'], ['chain', 'filesystem', 'asset_mapper'])) {
                        // Možnosť automaticky vytvoriť default resolver ak je to potrebné,
                        // alebo nechať na validáciu neskôr.
                    }

                    return $v;
                })
            ->end()
            ->children()
                ->arrayNode('resolvers')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->performNoDeepMerging()
                        ->children()
                            ->enumNode('type')
                                ->values(['filesystem', 'asset_mapper', 'chain'])
                                ->isRequired()
                            ->end()
                            // pre filesystem resolver
                            ->arrayNode('roots')
                                ->scalarPrototype()->end()
                            ->end()
                            ->booleanNode('allowUnresolvable')->defaultFalse()->end()
                            // pre chain resolver
                            ->arrayNode('resolvers')
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                        ->validate()
                            ->ifTrue(function ($v) {
                                return 'filesystem' === $v['type'] && empty($v['roots']);
                            })
                            ->thenInvalid('The "roots" option must be defined for "filesystem" resolver.')
                        ->end()
                        ->validate()
                            ->ifTrue(function ($v) {
                                return 'chain' === $v['type'] && empty($v['resolvers']);
                            })
                            ->thenInvalid('The "resolvers" option must be defined for "chain" resolver.')
                        ->end()
                    ->end()
                ->end()
                ->enumNode('driver')
                    ->values(['gd', 'imagick'])
                    ->defaultValue('gd')
                ->end()
                ->scalarNode('loader')->defaultNull()->end()
                ->scalarNode('resolver')->defaultNull()->end()
                ->scalarNode('cache')->defaultNull()->end()
                ->scalarNode('image_cache_service')->defaultValue('cache.app')->end()
                ->arrayNode('hash_resolution')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('width')->defaultValue(10)->end()
                        ->integerNode('height')->defaultValue(8)->end()
                    ->end()
                ->end()
                ->scalarNode('fallback_image')->defaultNull()->end()
                ->booleanNode('image_cache_enabled')->defaultFalse()->end()
                ->integerNode('ttl')->defaultNull()->end()
                ->arrayNode('responsive_strategy')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('grid')
                            ->addDefaultsIfNotSet()
                            ->beforeNormalization()
                                ->ifArray()
                                ->then(function ($v) {
                                    $framework = $v['framework'] ?? 'custom';
                                    $defaults = [];

                                    if ('bootstrap' === $framework) {
                                        $defaults = [
                                            'columns' => 12,
                                            'gutter' => 24,
                                            'layouts' => [
                                                'xxl' => ['min_viewport' => 1400, 'max_container' => 1320],
                                                'xl' => ['min_viewport' => 1200, 'max_container' => 1140],
                                                'lg' => ['min_viewport' => 992, 'max_container' => 960],
                                                'md' => ['min_viewport' => 768, 'max_container' => 720],
                                                'sm' => ['min_viewport' => 576, 'max_container' => 540],
                                                'xs' => ['min_viewport' => 0, 'max_container' => null],
                                            ],
                                        ];
                                    } elseif ('tailwind' === $framework) {
                                        $defaults = [
                                            'columns' => 12,
                                            'gutter' => 0,
                                            'layouts' => [
                                                '2xl' => ['min_viewport' => 1536, 'max_container' => 1536],
                                                'xl' => ['min_viewport' => 1280, 'max_container' => 1280],
                                                'lg' => ['min_viewport' => 1024, 'max_container' => 1024],
                                                'md' => ['min_viewport' => 768, 'max_container' => 768],
                                                'sm' => ['min_viewport' => 640, 'max_container' => 640],
                                                'default' => ['min_viewport' => 0, 'max_container' => null],
                                            ],
                                        ];
                                    }

                                    return array_replace_recursive($defaults, $v);
                                })
                            ->end()
                            ->children()
                                ->enumNode('framework')
                                    ->values(['bootstrap', 'tailwind', 'custom'])
                                    ->defaultValue('custom')
                                ->end()
                                ->integerNode('columns')->defaultValue(12)->end()
                                ->integerNode('gutter')->defaultValue(24)->end()
                                ->arrayNode('layouts')
                                    ->useAttributeAsKey('name')
                                    ->arrayPrototype()
                                        ->children()
                                            ->integerNode('min_viewport')->end()
                                            ->scalarNode('max_container')
                                                ->defaultNull() // null = 100vw
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
			->arrayNode('ratios')
			->useAttributeAsKey('name')
			->scalarPrototype()->end()
			->end()
                    ->end()
                ->end()
                ->arrayNode('path_decorators')
                    ->scalarPrototype()->end()
                ->end()
            ->end()
        ->end()
        ;

        return $treeBuilder;
    }
}
