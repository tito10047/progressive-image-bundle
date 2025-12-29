<?php

use Tito10047\ProgressiveImageBundle\Analyzer\GdImageAnalyzer;
use Tito10047\ProgressiveImageBundle\Analyzer\ImagickAnalyzer;
use Tito10047\ProgressiveImageBundle\Loader\FileSystemLoader;
use Tito10047\ProgressiveImageBundle\Resolver\AssetMapperResolver;
use Tito10047\ProgressiveImageBundle\Resolver\FileSystemResolver;
use Tito10047\ProgressiveImageBundle\Service\MetadataReader;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * @link https://symfony.com/doc/current/bundles/best_practices.html#services
 */
return static function (ContainerConfigurator $container): void {
    $container
        ->parameters()
            // ->set('tito10047_progressive.param_name', 'param_value');
    ;
    $services = $container->services()
    ;

    $services->set(MetadataReader::class)
        ->public()
		->arg('$dispatcher', service('event_dispatcher'))
        ->arg('$ttl', null)
        ->arg('$fallbackPath', null)
    ;

    $services->set('progressive_image.filesystem.loader', FileSystemLoader::class);

    $services->set('progressive_image.analyzer.gd',GdImageAnalyzer::class);
    $services->set('progressive_image.analyzer.imagick',ImagickAnalyzer::class);

    $services->set('progressive_image.resolver.filesystem',FileSystemResolver::class);
    $services->set('progressive_image.resolver.asset_mapper',AssetMapperResolver::class);
};
