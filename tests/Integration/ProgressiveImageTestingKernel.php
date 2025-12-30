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
use Tito10047\ProgressiveImageBundle\ProgressiveImageBundle;

class ProgressiveImageTestingKernel extends Kernel {

	public function __construct(
		private readonly array $options = []
	) {
		parent::__construct('test', true);
	}

	public function registerBundles(): iterable {
		return [
			new FrameworkBundle(),
			new TwigComponentBundle(),
			new TwigBundle(),
			new StimulusBundle(),
			new ProgressiveImageBundle(),
			new LiipImagineBundle(),
		];
	}

	public function registerContainerConfiguration(LoaderInterface $loader): void {
		$loader->load(function (ContainerBuilder $container) {
			$container->loadFromExtension('framework', [
				'secret'                => 'F00',
				'test'                  => true,
				'handle_all_throwables' => true,
				'php_errors'            => [
					'log' => true,
				],
				'router' => [
					'resource' => 'kernel::loadRoutes',
					'type' => 'service',
					'utf8' => true,
				],
				'http_method_override' => false,
			]);
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

	public function getCacheDir(): string {
		return __DIR__ . '/../../var/cache/tests/' . spl_object_hash($this);
	}
}