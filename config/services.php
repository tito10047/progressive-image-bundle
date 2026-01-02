<?php

use Tito10047\ProgressiveImageBundle\Event\TransparentImageCacheSubscriber;
use Tito10047\ProgressiveImageBundle\Twig\TransparentCacheExtension;
use Liip\ImagineBundle\LiipImagineBundle;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\HttpFoundation\UriSigner;
use Tito10047\ProgressiveImageBundle\Controller\LiipImagineController;
use Tito10047\ProgressiveImageBundle\Event\KernelResponseEventListener;
use Tito10047\ProgressiveImageBundle\Service\LiipImagineRuntimeConfigGenerator;
use Tito10047\ProgressiveImageBundle\Service\PreloadCollector;
use Tito10047\ProgressiveImageBundle\Analyzer\GdImageAnalyzer;
use Tito10047\ProgressiveImageBundle\Analyzer\ImagickAnalyzer;
use Tito10047\ProgressiveImageBundle\Decorators\LiipImagineDecorator;
use Tito10047\ProgressiveImageBundle\Loader\FileSystemLoader;
use Tito10047\ProgressiveImageBundle\Resolver\AssetMapperResolver;
use Tito10047\ProgressiveImageBundle\Resolver\FileSystemResolver;
use Tito10047\ProgressiveImageBundle\Service\MetadataReader;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tito10047\ProgressiveImageBundle\UrlGenerator\LiipImagineResponsiveImageUrlGenerator;
use Tito10047\ProgressiveImageBundle\UrlGenerator\ResponsiveImageUrlGeneratorInterface;
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
		->arg('$cache', service('cache.app'))
		->arg('$analyzer', service('progressive_image.analyzer.gd'))
		->arg('$loader', service('progressive_image.filesystem.loader'))
		->arg('$pathResolver', service('progressive_image.resolver.default'))
        ->arg('$ttl', null)
        ->arg('$fallbackPath', null)
    ;

    $services->set('progressive_image.filesystem.loader', FileSystemLoader::class);

    $services->set('progressive_image.analyzer.gd',GdImageAnalyzer::class);
    $services->set('progressive_image.analyzer.imagick',ImagickAnalyzer::class);

    $services->set('progressive_image.resolver.filesystem',FileSystemResolver::class);
    $services->set('progressive_image.resolver.asset_mapper',AssetMapperResolver::class);

    if (class_exists(LiipImagineBundle::class)) {
        $services->set('progressive_image.decorator.liip_imagine', LiipImagineDecorator::class)
            ->arg('$cache', service('liip_imagine.cache.manager'))
            ->arg('$configuration', service('liip_imagine.filter.configuration'))
        ;

		$services->set(LiipImagineRuntimeConfigGenerator::class)
			->arg('$filterConfiguration', service('liip_imagine.filter.configuration'))
		;

		$services->set(LiipImagineResponsiveImageUrlGenerator::class)
			->arg('$cacheManager', service('liip_imagine.cache.manager'))
			->arg('$router', service('router'))
			->arg('$uriSigner', service('uri_signer'))
			->arg('$runtimeConfigGenerator', service(LiipImagineRuntimeConfigGenerator::class))
			->arg('$filterConfiguration', service('liip_imagine.filter.configuration'))
			->public()
		;

        $services->alias(ResponsiveImageUrlGeneratorInterface::class, LiipImagineResponsiveImageUrlGenerator::class);

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

    $services->set(TransparentCacheExtension::class)
        ->arg('$cache', service('progressive_image.image_cache_service'))
        ->arg('$ttl', new Parameter('progressive_image.ttl'))
        ->tag('twig.extension')
    ;

    $services->set(TransparentImageCacheSubscriber::class)
        ->arg('$cache', service('progressive_image.image_cache_service'))
        ->arg('$enabled', new Parameter('progressive_image.image_cache_enabled'))
        ->arg('$ttl', new Parameter('progressive_image.ttl'))
        ->tag('kernel.event_subscriber')
    ;
};
