<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Integration;

use Liip\ImagineBundle\LiipImagineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Tito10047\ProgressiveImageBundle\ProgressiveImageBundle;

class ProgressiveImageTestingKernel extends Kernel {
	use MicroKernelTrait{
		registerContainerConfiguration as microKernelRegisterContainerConfiguration;
	}

	public function __construct(
		private readonly array $options = []
	) {
		parent::__construct('test', true);
	}

	public function registerBundles(): iterable {
		$bundles = [
			new FrameworkBundle(),
			new TwigComponentBundle(),
			new TwigBundle(),
			new StimulusBundle(),
			new ProgressiveImageBundle(),
		];

		if (class_exists(LiipImagineBundle::class)) {
			$bundles[] = new LiipImagineBundle();
		}

		return $bundles;
	}

	public function registerContainerConfiguration(LoaderInterface $loader): void {
		$this->microKernelRegisterContainerConfiguration($loader);
		$loader->load(function (ContainerBuilder $container) {
			$container->loadFromExtension('framework', [
				'secret'                => 'F00',
				'test'                  => true,
				'handle_all_throwables' => true,
				'php_errors'            => [
					'log' => true,
				],
				'http_method_override' => false,
			]);

            $container->setAlias('test.service_container', 'service_container')->setPublic(true);

			if (class_exists(LiipImagineBundle::class)) {
				$container->loadFromExtension('liip_imagine', [
					"loaders"   => [
						"default" => [
							"filesystem" => [
								"data_root" => "%kernel.project_dir%/tests/Functional/Fixtures/images"
							]
						]
					],
					"filter_sets" => [
						"cache"       => [],
						"preview_big" => [
							"quality" => 75,
							"filters" => [
								"thumbnail" => [
									"size" => [20, 20],
									"mode" => "outbound"
								]
							]
						]
					]
				]);
			}

			$container->loadFromExtension('framework', [
				'secret'                => 'F00',
				'test'                  => true,
				'http_method_override'  => true,
				'handle_all_throwables' => true,
				'php_errors'            => [
					'log' => true,
				],
			]);
			foreach($this->options as $bundle=>$options) {
				$container->loadFromExtension($bundle, $options);
			}
		});
	}


    public function loadRoutes(LoaderInterface $loader): RouteCollection
    {
        $routes = new RouteCollection();
        $routes->add('progressive_image_filter', new Route('/progressive-image', [
            '_controller' => \Tito10047\ProgressiveImageBundle\Controller\LiipImagineController::class . '::index',
        ]));

        return $routes;
    }

	public function getCacheDir(): string {
		return __DIR__ . '/../../var/cache/tests/' . spl_object_hash($this);
	}

	public function shutdown(): void {
		parent::shutdown();
		$cacheDir = $this->getCacheDir();
		if (is_dir($cacheDir)) {
			$this->removeDir($cacheDir);
		}
	}

	private function removeDir(string $dir): void {
		$files = array_diff(scandir($dir), ['.', '..']);
		foreach ($files as $file) {
			$path = $dir . '/' . $file;
			is_dir($path) ? $this->removeDir($path) : unlink($path);
		}
		rmdir($dir);
	}


}