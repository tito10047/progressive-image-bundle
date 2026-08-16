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
                            // for filesystem resolver
                            ->arrayNode('roots')
                                ->scalarPrototype()->end()
                            ->end()
                            ->booleanNode('allowUnresolvable')->defaultFalse()->end()
                            // for chain resolver
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
                ->scalarNode('resolver')->defaultValue('default')->end()
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
                ->arrayNode('retina')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->arrayNode('multipliers')
                            ->integerPrototype()->end()
                            ->defaultValue([1, 2])
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('responsive_strategy')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('generator')
                            ->defaultNull()
                            ->info('Service id of a custom ResponsiveImageUrlGeneratorInterface implementation. Overrides the default/Variant-pipeline generator when set.')
                        ->end()
                        ->integerNode('fluid_max_width')
                            ->defaultValue(1920)
                            ->info('Assumed max viewport width (px) used to estimate the pixel width of fluid (vw-based) breakpoints when generating variant URLs.')
                        ->end()
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
                                                'xs' => ['min_viewport' => 0, 'max_container' => null],
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
                ->arrayNode('image_configs')
                    ->variablePrototype()->end()
                ->end()
                ->scalarNode('secret')->defaultNull()->end()
                ->arrayNode('variant_store')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('storage')->defaultNull()->end()
                        ->scalarNode('prefix')->defaultValue('')->end()
                        ->scalarNode('public_url_prefix')->defaultValue('/media/pgi')->end()
                        ->integerNode('fail_marker_ttl')->defaultValue(300)->end()
                    ->end()
                ->end()
                ->arrayNode('variant_source')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('http')
                            ->addDefaultsIfNotSet()
                            ->info('Remote (http/https) source loading. An SSRF surface by nature, so it stays off unless allowed_hosts is explicitly populated.')
                            ->children()
                                ->booleanNode('enabled')->defaultFalse()->end()
                                ->arrayNode('allowed_hosts')
                                    ->scalarPrototype()->end()
                                ->end()
                                ->integerNode('timeout')->defaultValue(5)->end()
                            ->end()
                            ->validate()
                                ->ifTrue(static fn ($v) => true === $v['enabled'] && empty($v['allowed_hosts']))
                                ->thenInvalid('variant_source.http.allowed_hosts must not be empty when variant_source.http.enabled is true — remote source loading is an SSRF surface and must be explicitly allowlisted.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('generation')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('strategy')
                            ->values(['async', 'sync', 'terminate'])
                            ->defaultValue('async')
                        ->end()
                        ->scalarNode('transport')->defaultValue('async_images')->end()
                        ->enumNode('fallback_while_pending')
                            ->values(['original', 'wait'])
                            ->defaultValue('original')
                        ->end()
                        ->scalarNode('lock_store')->defaultNull()->end()
                    ->end()
                ->end()
                ->arrayNode('formats')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('default')
                            ->values(['jpeg', 'png', 'webp', 'avif'])
                            ->defaultValue('jpeg')
                        ->end()
                        ->integerNode('default_quality')->defaultValue(85)->end()
                        ->arrayNode('negotiate')
                            ->info('Formats tried in order against the request Accept header before falling back to "default".')
                            ->enumPrototype()->values(['jpeg', 'png', 'webp', 'avif'])->end()
                            ->defaultValue([])
                        ->end()
                        ->arrayNode('picture')
                            ->info('Formats rendered as extra <picture><source type="image/..."> candidates (most preferred first), in addition to the plain/default-format fallback. Empty = disabled (today\'s behavior).')
                            ->enumPrototype()->values(['jpeg', 'png', 'webp', 'avif'])->end()
                            ->defaultValue([])
                        ->end()
                        ->arrayNode('quality')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->integerNode('jpeg')->defaultValue(85)->end()
                                ->integerNode('webp')->defaultValue(82)->end()
                                ->integerNode('avif')->defaultValue(60)->end()
                                ->integerNode('png')->defaultValue(90)->end()
                            ->end()
                        ->end()
                        ->booleanNode('progressive')
                            ->defaultFalse()
                            ->info('JPEG: progressive encoding. PNG: Adam7 interlacing. No effect on WebP/AVIF (no such concept for either format).')
                        ->end()
                        ->booleanNode('strip_metadata')
                            ->defaultFalse()
                            ->info('Strips EXIF/metadata on encode. JPEG/WebP/AVIF only — Intervention\'s PNG encoder has no such option.')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('filter_sets')
                    ->useAttributeAsKey('name')
                    ->variablePrototype()->end()
                ->end()
                ->arrayNode('post_processors')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('jpegoptim')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')->defaultFalse()->end()
                                ->scalarNode('bin')->defaultValue('jpegoptim')->end()
                            ->end()
                        ->end()
                        ->arrayNode('pngquant')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')->defaultFalse()->end()
                                ->scalarNode('bin')->defaultValue('pngquant')->end()
                            ->end()
                        ->end()
                        ->arrayNode('cwebp')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')->defaultFalse()->end()
                                ->scalarNode('bin')->defaultValue('cwebp')->end()
                            ->end()
                        ->end()
                        ->arrayNode('avifenc')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')->defaultFalse()->end()
                                ->scalarNode('bin')->defaultValue('avifenc')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ->end()
        ;

        return $treeBuilder;
    }
}
