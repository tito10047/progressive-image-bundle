<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Tests\Integration\Loader;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Exception\PathResolutionException;
use Tito10047\ProgressiveImageBundle\Loader\FileSystemLoader;

class FileSystemLoaderTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'test_image');
        file_put_contents($this->tempFile, 'test data');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testLoadReturnsResource(): void
    {
        $loader = new FileSystemLoader();
        $resource = $loader->load($this->tempFile);

        $this->assertIsResource($resource);
        $this->assertSame('test data', stream_get_contents($resource));
    }

    public function testLoadThrowsExceptionForNonExistentFile(): void
    {
        $loader = new FileSystemLoader();

        $this->expectException(PathResolutionException::class);
        $loader->load('/non/existent/path/image.jpg');
    }
}
