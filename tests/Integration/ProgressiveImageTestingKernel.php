<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Tito10047\ProgressiveImageBundle\ProgressiveImageBundle;

class ProgressiveImageTestingKernel extends Kernel {

	public function __construct(
		private readonly array $knpUIpsumConfig = []
	) {
		parent::__construct('test', true);
	}

	public function registerBundles(): iterable {
		return [
			new FrameworkBundle(),
			new TwigComponentBundle(),
			new TwigBundle(),
			new ProgressiveImageBundle(),
		];
	}

	public function registerContainerConfiguration(LoaderInterface $loader): void {
		$loader->load(function (ContainerBuilder $container) {
			$container->loadFromExtension('framework', [
				'secret'                => 'F00',
				'test'                  => true,
				'http_method_override'  => true,
				'handle_all_throwables' => true,
				'php_errors'            => [
					'log' => true,
				],
			]);
			$container->loadFromExtension('twig_component', [
				'anonymous_template_directory' => 'components/',
				'defaults' => [
					'App\Twig\Components\\'=> 'components/'
				]
			]);
			$container->loadFromExtension('progressive_image', $this->knpUIpsumConfig);
		});
	}

	public function getCacheDir(): string {
		return __DIR__ . '/../../var/cache/tests/' . spl_object_hash($this);
	}
}