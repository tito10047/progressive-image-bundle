<?php

use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\HttpFoundation\UriSigner;
use Tito10047\ProgressiveImageBundle\Event\KernelResponseEventListener;
use Tito10047\ProgressiveImageBundle\Service\PreloadCollector;
use Tito10047\ProgressiveImageBundle\Analyzer\GdImageAnalyzer;
use Tito10047\ProgressiveImageBundle\Analyzer\ImagickAnalyzer;
use Tito10047\ProgressiveImageBundle\Decorators\LiipImagineDecorator;
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

	$services->set('progressive_image.decorator.liip_imagine', LiipImagineDecorator::class)
		->arg('$cache', service('liip_imagine.cache.manager'))
		->arg('$configuration', service('liip_imagine.filter.configuration'))
	;

	$services->set(PreloadCollector::class)
		->arg('$requestStack', service('request_stack'))
	;

	$services->set(KernelResponseEventListener::class)
		->arg('$preloadCollector', service(PreloadCollector::class))
		->tag('kernel.event_listener', ['event' => 'kernel.response'])
	;
    
    if (class_exists(\Liip\ImagineBundle\LiipImagineBundle::class)) {
		$services->set(\Tito10047\ProgressiveImageBundle\Service\LiipImagineRuntimeConfigGenerator::class)
			->arg('$filterConfiguration', service('liip_imagine.filter.configuration'))
		;

		$services->set(\Tito10047\ProgressiveImageBundle\UrlGenerator\LiipImagineResponsiveImageUrlGenerator::class)
			->arg('$cacheManager', service('liip_imagine.cache.manager'))
			->arg('$router', service('router'))
			->arg('$uriSigner', service('uri_signer'))
			->arg('$runtimeConfigGenerator', service(\Tito10047\ProgressiveImageBundle\Service\LiipImagineRuntimeConfigGenerator::class))
			->arg('$filterConfiguration', service('liip_imagine.filter.configuration'))
			->public()
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
        $services->set(\Tito10047\ProgressiveImageBundle\Controller\LiipImagineController::class)
            ->arg('$signer', service('uri_signer'))
            ->arg('$filterService', service('liip_imagine.service.filter'))
            ->arg('$dataManager', service('liip_imagine.data.manager'))
            ->arg('$filterConfiguration', service('liip_imagine.filter.configuration'))
            ->arg('$controllerConfig', service('liip_imagine.controller.config'))
            ->arg('$runtimeConfigGenerator', service(\Tito10047\ProgressiveImageBundle\Service\LiipImagineRuntimeConfigGenerator::class))
            ->public()
        ;
    }
};
