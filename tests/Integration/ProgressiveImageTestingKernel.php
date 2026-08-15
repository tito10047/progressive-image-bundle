<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Tito10047\ProgressiveImageBundle\ProgressiveImageBundle;
use Tito10047\ProgressiveImageBundle\Tests\Fixtures\FakeDimensionsEchoingUrlGenerator;
use Tito10047\ProgressiveImageBundle\Tests\Fixtures\FakeFilterPathDecorator;

class ProgressiveImageTestingKernel extends Kernel
{
    use MicroKernelTrait{
        registerContainerConfiguration as microKernelRegisterContainerConfiguration;
    }

    private ?\Closure $customConfiguration = null;

    public function __construct(
        private array $options = [],
    ) {
        parent::__construct('test', true);
    }

    public function setCustomConfiguration(?\Closure $customConfiguration): void
    {
        $this->customConfiguration = $customConfiguration;
    }

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new TwigComponentBundle(),
            new TwigBundle(),
            new StimulusBundle(),
            new ProgressiveImageBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $this->microKernelRegisterContainerConfiguration($loader);
        $loader->load(function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'secret' => 'F00',
                'test' => true,
                'handle_all_throwables' => true,
                'php_errors' => [
                    'log' => true,
                ],
                'http_method_override' => false,
                'cache' => [
                    'app' => 'cache.adapter.array',
                    'pools' => [
                        'my_custom_cache_pool' => [
                            'adapter' => 'cache.adapter.array',
                            'public' => true,
                            'tags' => true,
                        ],
                    ],
                ],
            ]);

            //            $container->setAlias('test.service_container', 'service_container')->setPublic(true);

            $container->register('test.fake_filter_path_decorator', FakeFilterPathDecorator::class)
                ->setPublic(true);
            $container->register('test.fake_dimensions_url_generator', FakeDimensionsEchoingUrlGenerator::class)
                ->setPublic(true);

            $container->loadFromExtension('twig_component', [
                'anonymous_template_directory' => 'components/',
                'defaults' => [
                    'App\Twig\Components\\' => '%kernel.project_dir%/tests/Functional/Fixtures/templates/components/',
                ],
            ]);
            if (!array_key_exists('progressive_image', $this->options)) {
                $this->options['progressive_image'] = [];
            }
            foreach ($this->options as $bundle => $options) {
                if ('progressive_image' == $bundle) {
                    $options += [
                        'image_cache_enabled' => true,
                        'image_cache_service' => 'my_custom_cache_pool',
                    ];
                }
                $container->loadFromExtension($bundle, $options);
            }

            if ($this->customConfiguration) {
                ($this->customConfiguration)($container);
            }
        });
    }

    public function loadRoutes(LoaderInterface $loader): RouteCollection
    {
        $routes = new RouteCollection();

        $routes->add('pgi_variant_serve', new Route('/media/pgi/wait', [
            '_controller' => \Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Presentation\Controller\ImageVariantController::class.'::serve',
        ]));

        return $routes;
    }

    public function getCacheDir(): string
    {
        return __DIR__.'/../../var/cache/tests/'.spl_object_hash($this);
    }

    public function shutdown(): void
    {
        parent::shutdown();
        //        $cacheDir = $this->getCacheDir();
        //        if (is_dir($cacheDir)) {
        //            $this->removeDir($cacheDir);
        //        }
    }

    private function removeDir(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
