<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Tito10047\ProgressiveImageBundle\Tests\Integration\Controller;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\UriSigner;
use Tito10047\ProgressiveImageBundle\Tests\Integration\PGIWebTestCase;
use Tito10047\ProgressiveImageBundle\UrlGenerator\LiipImagineResponsiveImageUrlGenerator;

class LiipImagineControllerTest extends PGIWebTestCase
{
    private string $tempDir;
    private Filesystem $fs;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->tempDir = sys_get_temp_dir().'/progressive_image_test_controller_'.uniqid();
        $this->fs->mkdir($this->tempDir);
        $this->fs->copy(__DIR__.'/../../Fixtures/test.png', $this->tempDir.'/test.png');
    }

    protected function tearDown(): void
    {
        if (isset($this->tempDir) && $this->fs->exists($this->tempDir)) {
            $this->fs->remove($this->tempDir);
        }
        // Also cleanup public/media/cache if it exists in project root for tests
        $publicCache = __DIR__.'/../../../public/media/cache';
        if ($this->fs->exists($publicCache)) {
            $this->fs->remove($publicCache); // Maybe too dangerous? Better keep it or clean subdirs
        }
        parent::tearDown();
    }

    public function testIndexWithFilter(): void
    {
        if (!class_exists(CacheManager::class)) {
            $this->markTestSkipped('LiipImagineBundle is not installed.');
        }
        $client = static::createClient([
            'progressive_image' => [
                'path_decorators' => [
                    'progressive_image.decorator.liip_imagine',
                ],
                'resolvers' => [
                    'temp' => [
                        'type' => 'filesystem',
                        'roots' => [$this->tempDir],
                    ],
                ],
                'resolver' => 'temp',
            ],
            'liip_imagine' => [
                'loaders' => [
                    'default' => [
                        'filesystem' => [
                            'data_root' => $this->tempDir,
                        ],
                    ],
                ],
            ],
        ]);

        $container = $client->getContainer();

        /** @var UriSigner $signer */
        $signer = $container->get('uri_signer');

        $path = 'test.png';
        $width = 100;
        $height = 100;
        $filter = 'preview_big';

        $url = sprintf('/progressive-image?path=%s&width=%d&height=%d&filter=%s', $path, $width, $height, $filter);
        $signedUrl = $signer->sign('http://localhost'.$url);

        $client->request('GET', $signedUrl);

        $this->assertResponseRedirects();
        $redirectUrl = $client->getResponse()->headers->get('Location');
        $this->assertStringContainsString('/media/cache/preview_big_100x100/', $redirectUrl);
        $this->assertStringNotContainsString('/rc/', $redirectUrl);

        // Verify physical file exists
        $projectDir = $container->getParameter('kernel.project_dir');
        $relativeFilePath = parse_url($redirectUrl, PHP_URL_PATH);
        $absoluteFilePath = $projectDir.'/public'.$relativeFilePath;

        $this->assertFileExists($absoluteFilePath);

        // Verify image size
        $imageSize = getimagesize($absoluteFilePath);
        $this->assertEquals(100, $imageSize[0]);
        $this->assertEquals(100, $imageSize[1]);
    }

    public function testIndexWithPointInterest(): void
    {
        if (!class_exists(CacheManager::class)) {
            $this->markTestSkipped('LiipImagineBundle is not installed.');
        }
        $client = static::createClient([
            'progressive_image' => [
                'resolvers' => [
                    'temp' => [
                        'type' => 'filesystem',
                        'roots' => [$this->tempDir],
                    ],
                ],
                'resolver' => 'temp',
            ],
            'liip_imagine' => [
                'loaders' => [
                    'default' => [
                        'filesystem' => [
                            'data_root' => $this->tempDir,
                        ],
                    ],
                ],
            ],
        ]);
        $container = $client->getContainer();
        /** @var UriSigner $signer */
        $signer = $container->get('uri_signer');

        $path = 'test.png';
        $width = 50;
        $height = 50;
		$poi = '0x0'; // Upper left corner

        $url = sprintf('/progressive-image?path=%s&width=%d&height=%d&pointInterest=%s', $path, $width, $height, $poi);
        $signedUrl = $signer->sign('http://localhost'.$url);

        $client->request('GET', $signedUrl);

        $this->assertResponseRedirects();
        $redirectUrl = $client->getResponse()->headers->get('Location');
        $this->assertStringContainsString('/media/cache/50x50_0x0/', $redirectUrl);

        // Verify physical file exists
        $projectDir = $container->getParameter('kernel.project_dir');
        $relativeFilePath = parse_url($redirectUrl, PHP_URL_PATH);
        $absoluteFilePath = $projectDir.'/public'.$relativeFilePath;

        $this->assertFileExists($absoluteFilePath);

        // Verify image size
        $imageSize = getimagesize($absoluteFilePath);
        $this->assertEquals(50, $imageSize[0]);
        $this->assertEquals(50, $imageSize[1]);
    }

    public function testPointInterestCropping(): void
    {
        if (!class_exists(CacheManager::class)) {
            $this->markTestSkipped('LiipImagineBundle is not installed.');
        }

		// 1. Create a black 100x100 image with one white pixel at 75, 25
        $origW = 100;
        $origH = 100;
        $poiX_percent = 75;
        $poiY_percent = 25;
        $pixelX = (int) ($poiX_percent / 100 * $origW); // 75
        $pixelY = (int) ($poiY_percent / 100 * $origH); // 25

        $img = imagecreatetruecolor($origW, $origH);
        $black = imagecolorallocate($img, 0, 0, 0);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $black);
        imagesetpixel($img, $pixelX, $pixelY, $white);

        $imagePath = $this->tempDir.'/poi_test.png';
        imagepng($img, $imagePath);
        imagedestroy($img);

        $client = static::createClient([
            'progressive_image' => [
                'resolvers' => [
                    'temp' => [
                        'type' => 'filesystem',
                        'roots' => [$this->tempDir],
                    ],
                ],
                'resolver' => 'temp',
            ],
            'liip_imagine' => [
                'loaders' => [
                    'default' => [
                        'filesystem' => [
                            'data_root' => $this->tempDir,
                        ],
                    ],
                ],
            ],
        ]);
        $container = $client->getContainer();
        /** @var UriSigner $signer */
        $signer = $container->get('uri_signer');

        $targetW = 50;
        $targetH = 50;
        $poi = "{$poiX_percent}x{$poiY_percent}"; // "75x25"

        $url = sprintf('/progressive-image?path=%s&width=%d&height=%d&pointInterest=%s', 'poi_test.png', $targetW, $targetH, $poi);
        $signedUrl = $signer->sign('http://localhost'.$url);

        $client->request('GET', $signedUrl);

        $this->assertResponseRedirects();
        $redirectUrl = $client->getResponse()->headers->get('Location');

        $projectDir = $container->getParameter('kernel.project_dir');
        $relativeFilePath = parse_url($redirectUrl, PHP_URL_PATH);
        $absoluteFilePath = $projectDir.'/public'.$relativeFilePath;

        $this->assertFileExists($absoluteFilePath);

		// 2. Check if the white pixel is in the center of the resulting 50x50 image
		// Center of 50x50 is 25, 25
        $resultImg = imagecreatefrompng($absoluteFilePath);
        $rgb = imagecolorat($resultImg, 25, 25);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

		$this->assertEquals(255, $r, 'Middle pixel should be white (R)');
		$this->assertEquals(255, $g, 'Middle pixel should be white (G)');
		$this->assertEquals(255, $b, 'Middle pixel should be white (B)');

		// Check some other pixel if it is black
        $rgbBlack = imagecolorat($resultImg, 0, 0);
		$this->assertEquals(0, $rgbBlack & 0xFF, 'Corner pixel should be black');

        imagedestroy($resultImg);
    }

	public function testIndexWithSignedUrlFromGenerator(): void {
		if (!class_exists(CacheManager::class)) {
			$this->markTestSkipped('LiipImagineBundle is not installed.');
		}
		$client = static::createClient([
			'progressive_image' => [
				'path_decorators' => [
					'progressive_image.decorator.liip_imagine',
				],
				'resolvers'       => [
					'temp' => [
						'type'  => 'filesystem',
						'roots' => [$this->tempDir],
					],
				],
				'resolver'        => 'temp',
			],
			'liip_imagine'      => [
				'loaders' => [
					'default' => [
						'filesystem' => [
							'data_root' => $this->tempDir,
						],
					],
				],
			],
		]);

		$container = $client->getContainer();

		/** @var LiipImagineResponsiveImageUrlGenerator $generator */
		$generator = $container->get(LiipImagineResponsiveImageUrlGenerator::class);

		$path   = 'test.png';
		$width  = 60;
		$height = 60;

		$signedUrl = $generator->generateUrl($path, $width, $height);

		$client->request('GET', $signedUrl);

		$this->assertResponseRedirects();
		$redirectUrl = $client->getResponse()->headers->get('Location');
		$this->assertStringContainsString('/media/cache/60x60/', $redirectUrl);

		// Verify physical file exists
		$projectDir       = $container->getParameter('kernel.project_dir');
		$relativeFilePath = parse_url($redirectUrl, PHP_URL_PATH);
		$absoluteFilePath = $projectDir . '/public' . $relativeFilePath;

		$this->assertFileExists($absoluteFilePath);

		// Verify image size
		$imageSize = getimagesize($absoluteFilePath);
		$this->assertEquals(60, $imageSize[0]);
		$this->assertEquals(60, $imageSize[1]);
	}

	public function testGenerateImageProcess(): void {
		if (!class_exists(CacheManager::class)) {
			$this->markTestSkipped('LiipImagineBundle is not installed.');
		}
		$client = static::createClient([
			'progressive_image' => [
				'path_decorators' => [
					'progressive_image.decorator.liip_imagine',
				],
				'resolvers'       => [
					'temp' => [
						'type'  => 'filesystem',
						'roots' => [$this->tempDir],
					],
				],
				'resolver'        => 'temp',
			],
			'liip_imagine'      => [
				'loaders' => [
					'default' => [
						'filesystem' => [
							'data_root' => $this->tempDir,
						],
					],
				],
			],
		]);

		$container = $client->getContainer();
		/** @var UriSigner $signer */
		$signer = $container->get('uri_signer');

		$path   = 'test.png';
		$width  = 80;
		$height = 80;

		// 1. vygeneruj obrazok cez controller aby sa ulozil na disk
		$url       = sprintf('/progressive-image?path=%s&width=%d&height=%d', $path, $width, $height);
		$signedUrl = $signer->sign('http://localhost' . $url);
		$client->request('GET', $signedUrl);

		$this->assertResponseRedirects();
		$redirectUrl = $client->getResponse()->headers->get('Location');
		$this->assertStringContainsString('/media/cache/80x80/', $redirectUrl);
		$this->assertStringNotContainsString('/resolve/', $redirectUrl, 'URL by nemala obsahovat /resolve/');
		$projectDir       = $container->getParameter('kernel.project_dir');
		$relativeFilePath = parse_url($redirectUrl, PHP_URL_PATH);
		$absoluteFilePath = $projectDir . '/public' . $relativeFilePath;

		$this->assertFileExists($absoluteFilePath, 'Obrazok by mal existovat na disku po volani controllera');

		// 3. pouzi LiipImagineResponsiveImageUrlGenerator aby ti vratila url adresu obrazku
		/** @var LiipImagineResponsiveImageUrlGenerator $generator */
		$generator    = $container->get(LiipImagineResponsiveImageUrlGenerator::class);
		$generatedUrl = $generator->generateUrl($path, $width, $height);

		// 4. skontroluj ci adresa ktora ti vratil url generator smeruje na obrazok
		$this->assertEquals($redirectUrl, $generatedUrl, 'URL z generatora by mala byt rovnaka ako URL na ktoru nas presmeroval controller');

		$relativeGeneratedPath = parse_url($generatedUrl, PHP_URL_PATH);
		$absoluteGeneratedPath = $projectDir . '/public' . $relativeGeneratedPath;
		$this->assertFileExists($absoluteGeneratedPath, 'URL z generatora musi smerovat na existujuci subor');
	}
}
