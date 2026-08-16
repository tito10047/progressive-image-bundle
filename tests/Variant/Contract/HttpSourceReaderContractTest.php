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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Contract;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Dimensions;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\SourceReader;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Source\HttpSourceReader;

final class HttpSourceReaderContractTest extends SourceReaderContractTestCase
{
    private const string EXISTING_URL = 'https://images.example.com/hero.png';
    private const string MISSING_URL = 'https://images.example.com/missing.png';

    private function pngBytes(): string
    {
        $image = imagecreatetruecolor(120, 80);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    protected function createReader(): SourceReader
    {
        $bytes = $this->pngBytes();

        $client = new MockHttpClient(function (string $method, string $url) use ($bytes): MockResponse {
            if (self::EXISTING_URL === $url) {
                return new MockResponse($bytes, ['http_code' => 200]);
            }

            return new MockResponse('', ['http_code' => 404]);
        });

        return new HttpSourceReader($client, ['images.example.com'], 5);
    }

    protected function existingSourcePath(): SourcePath
    {
        return new SourcePath(self::EXISTING_URL);
    }

    protected function expectedDimensions(): Dimensions
    {
        return new Dimensions(120, 80);
    }

    protected function expectedMime(): string
    {
        return 'image/png';
    }

    protected function missingSourcePath(): SourcePath
    {
        return new SourcePath(self::MISSING_URL);
    }
}
