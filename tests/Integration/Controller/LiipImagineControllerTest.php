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

class LiipImagineControllerTest extends AbstractLiipImagineControllerTestCase
{
    public function testIndexWithFilter(): void
    {
        $client = $this->createLiipClient();
        $signer = $this->getUriSigner($client);

        $path = 'test.png';
        $width = 100;
        $height = 100;
        $filter = 'preview_big';

        $url = sprintf('/progressive-image?path=%s&width=%d&height=%d&filter=%s', $path, $width, $height, $filter);
        $signedUrl = $signer->sign('http://localhost'.$url);

        $client->request('GET', $signedUrl);

        $this->assertImageRedirectAndProperties($client, '/media/cache/preview_big_100x100/', 100, 100);
    }

    public function testIndexWithCustomConfiguredFilter(): void
    {
        $client = $this->createLiipClient([
            'liip_imagine' => [
                'filter_sets' => [
                    'custom_filter' => [
                        'quality' => 80,
                        'filters' => [
                            'thumbnail' => [
                                'size' => [120, 120],
                                'mode' => 'outbound',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $signer = $this->getUriSigner($client);

        $path = 'test_custom.png';
        $this->fs->copy(__DIR__.'/../../Fixtures/test_800x800.png', $this->tempDir.'/'.$path);

        $width = 150;
        $height = 150;
        $filter = 'custom_filter';

        $url = sprintf('/progressive-image?path=%s&width=%d&height=%d&filter=%s', $path, $width, $height, $filter);
        $signedUrl = $signer->sign('http://localhost'.$url);

        $client->request('GET', $signedUrl);

        $this->assertImageRedirectAndProperties($client, '/media/cache/custom_filter_150x150/', 150, 150);
    }

    public function testIndexWithModifier(): void
    {
        $client = $this->createLiipClient();
        $signer = $this->getUriSigner($client);

        $path = 'test.png';
        $width = 100;
        $height = 100;
        $filter = 'preview_big';

        // Pridame circle=1 ako modifier, ktory by sa mal dostat do kontextu
        $url = sprintf('/progressive-image?path=%s&width=%d&height=%d&filter=%s&circle=1', $path, $width, $height, $filter);
        $signedUrl = $signer->sign('http://localhost'.$url);

        $client->request('GET', $signedUrl);

        $response = $client->getResponse();
        $this->assertTrue($response->isRedirect());
        $targetUrl = $response->headers->get('Location');
        // Ak bol circle=1 v kontexte, hash kontextu by mal byt v nazve filtra
        // Alebo ak by sme mali modifikator ktory meni priamo filter...
        // V nasom pripade Controller vola RuntimeConfigGenerator s kontextom,
        // ktory zahlti vsetky extra parametre.

        $this->assertStringContainsString('preview_big_100x100', $targetUrl);
        // Kedze context nie je prazdny (obsahuje circle=1), filterName by mal mat hash
        $this->assertMatchesRegularExpression('/preview_big_100x100_[a-f0-9]{5}/', $targetUrl);
    }
}
