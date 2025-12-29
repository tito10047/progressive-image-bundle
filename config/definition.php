<?php

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;

/**
 * @link https://symfony.com/doc/current/bundles/best_practices.html#configuration
 */
return static function (DefinitionConfigurator $definition): void {
    $definition
        ->rootNode()
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
                ->arrayNode('hash_resolution')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('width')->defaultValue(10)->end()
                        ->integerNode('height')->defaultValue(8)->end()
                    ->end()
                ->end()
                ->scalarNode('fallback_image')->defaultNull()->end()
                ->arrayNode('path_decorators')
                    ->scalarPrototype()->end()
                ->end()
            ->end()
        ->end()
    ;
};
