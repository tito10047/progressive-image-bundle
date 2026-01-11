<?php

declare(strict_types=1);

namespace Tito10047\ProgressiveImageBundle\Tests\Integration\Controller;

use Tito10047\ProgressiveImageBundle\UrlGenerator\LiipImagineResponsiveImageUrlGenerator;

class LiipImagineUrlGeneratorTest extends AbstractLiipImagineControllerTestCase {

	public function testIndexWithSignedUrlFromGenerator(): void {
		$client    = $this->createLiipClient();
		$container = $client->getContainer();

		/** @var LiipImagineResponsiveImageUrlGenerator $generator */
		$generator = $container->get(LiipImagineResponsiveImageUrlGenerator::class);

		$path   = 'test.png';
		$width  = 60;
		$height = 60;

		$signedUrl = $generator->generateUrl($path, $width, $height);

		$client->request('GET', $signedUrl);

		$this->assertImageRedirectAndProperties($client, '/media/cache/60x60/', 60, 60);
	}

	public function testGenerateImageProcess(): void {
		$client    = $this->createLiipClient();
		$container = $client->getContainer();
		$signer    = $this->getUriSigner($client);

		$path   = 'test.png';
		$width  = 80;
		$height = 80;

		// 1. vygeneruj obrazok cez controller aby sa ulozil na disk
		$url       = sprintf('/progressive-image?path=%s&width=%d&height=%d', $path, $width, $height);
		$signedUrl = $signer->sign('http://localhost' . $url);
		$client->request('GET', $signedUrl);

		$redirectUrl = $this->assertImageRedirectAndProperties($client, '/media/cache/80x80/', 80, 80);
		$this->assertStringNotContainsString('/resolve/', $redirectUrl, 'URL by nemala obsahovat /resolve/');

		// 3. pouzi LiipImagineResponsiveImageUrlGenerator aby ti vratila url adresu obrazku
		/** @var LiipImagineResponsiveImageUrlGenerator $generator */
		$generator    = $container->get(LiipImagineResponsiveImageUrlGenerator::class);
		$generatedUrl = $generator->generateUrl($path, $width, $height);

		// 4. skontroluj ci adresa ktora ti vratil url generator smeruje na obrazok
		$this->assertEquals($redirectUrl, $generatedUrl, 'URL z generatora by mala byt rovnaka ako URL na ktoru nas presmeroval controller');

		$projectDir            = $container->getParameter('kernel.project_dir');
		$relativeGeneratedPath = parse_url($generatedUrl, PHP_URL_PATH);
		$absoluteGeneratedPath = $projectDir . '/public' . $relativeGeneratedPath;
		$this->assertFileExists($absoluteGeneratedPath, 'URL z generatora musi smerovat na existujuci subor');
	}
}
