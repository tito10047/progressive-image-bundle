<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use Liip\ImagineBundle\LiipImagineBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\HttpFoundation\UriSigner;
use Tito10047\ProgressiveImageBundle\Analyzer\GdImageAnalyzer;
use Tito10047\ProgressiveImageBundle\Analyzer\ImagickAnalyzer;
use Tito10047\ProgressiveImageBundle\Controller\LiipImagineController;
use Tito10047\ProgressiveImageBundle\Decorators\LiipImagineDecorator;
use Tito10047\ProgressiveImageBundle\Event\KernelResponseEventListener;
use Tito10047\ProgressiveImageBundle\Loader\FileSystemLoader;
use Tito10047\ProgressiveImageBundle\Resolver\AssetMapperResolver;
use Tito10047\ProgressiveImageBundle\Resolver\FileSystemResolver;
use Tito10047\ProgressiveImageBundle\Service\LiipImagineRuntimeConfigGenerator;
use Tito10047\ProgressiveImageBundle\Service\MetadataReader;
use Tito10047\ProgressiveImageBundle\Service\PreloadCollector;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/*
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
        ->arg('$cache', service('cache.app'))
        ->arg('$analyzer', service('progressive_image.analyzer.gd'))
        ->arg('$loader', service('progressive_image.filesystem.loader'))
        ->arg('$pathResolver', service('progressive_image.resolver.default'))
        ->arg('$ttl', null)
        ->arg('$fallbackPath', null)
    ;

    $services->set('progressive_image.filesystem.loader', FileSystemLoader::class);

    $services->set('progressive_image.analyzer.gd', GdImageAnalyzer::class);
    $services->set('progressive_image.analyzer.imagick', ImagickAnalyzer::class);

    $services->set('progressive_image.resolver.filesystem', FileSystemResolver::class);
    $services->set('progressive_image.resolver.asset_mapper', AssetMapperResolver::class);

    if (class_exists(LiipImagineBundle::class)) {
        $services->set('progressive_image.decorator.liip_imagine', LiipImagineDecorator::class)
            ->arg('$cache', service('liip_imagine.cache.manager'))
            ->arg('$configuration', service('liip_imagine.filter.configuration'))
        ;

        $services->set(LiipImagineRuntimeConfigGenerator::class)
            ->arg('$filterConfiguration', service('liip_imagine.filter.configuration'))
        ;

        $services->set('uri_signer', UriSigner::class)
            ->args([
                new Parameter('kernel.secret'),
                '_hash',
                '_expiration',
                service('clock')->nullOnInvalid(),
            ])
            ->public()
            ->lazy()
            ->alias(UriSigner::class, 'uri_signer');
        $services->set(LiipImagineController::class)
            ->arg('$signer', service('uri_signer'))
            ->arg('$filterService', service('liip_imagine.service.filter'))
            ->arg('$dataManager', service('liip_imagine.data.manager'))
            ->arg('$filterConfiguration', service('liip_imagine.filter.configuration'))
            ->arg('$controllerConfig', service('liip_imagine.controller.config'))
            ->arg('$runtimeConfigGenerator', service(LiipImagineRuntimeConfigGenerator::class))
            ->arg('$metadataReader', service(MetadataReader::class))
            ->public()
        ;
    }

    $services->set(PreloadCollector::class)
        ->arg('$requestStack', service('request_stack'))
    ;

    $services->set(KernelResponseEventListener::class)
        ->arg('$preloadCollector', service(PreloadCollector::class))
        ->tag('kernel.event_listener', ['event' => 'kernel.response'])
    ;
};
