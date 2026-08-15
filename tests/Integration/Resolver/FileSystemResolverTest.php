<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Tests\Integration\Resolver;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Exception\PathResolutionException;
use Tito10047\ProgressiveImageBundle\Resolver\FileSystemResolver;

class FileSystemResolverTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/progressive_image_bundle_test';
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
        touch($this->tempDir.'/test.jpg');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempDir.'/test.jpg')) {
            unlink($this->tempDir.'/test.jpg');
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    public function testResolveFoundFile(): void
    {
        $resolver = new FileSystemResolver([$this->tempDir]);
        $result = $resolver->resolve('test.jpg');

        $this->assertSame(realpath($this->tempDir.'/test.jpg'), $result);
    }

    public function testResolveNotFoundThrowsException(): void
    {
        $resolver = new FileSystemResolver([$this->tempDir]);

        $this->expectException(PathResolutionException::class);
        $resolver->resolve('non-existent.jpg');
    }

    public function testResolveWithPlaceholder(): void
    {
        $resolver = new FileSystemResolver(['test_root' => $this->tempDir]);
        $result = $resolver->resolve('@test_root:test.jpg');

        $this->assertSame(realpath($this->tempDir.'/test.jpg'), $result);
    }

    public function testResolveRejectsPathsInASiblingDirectorySharingTheRootsNamePrefix(): void
    {
        // Sibling directory: "{$this->tempDir}-secret" is NOT inside $this->tempDir, but its
        // absolute path shares $this->tempDir as a plain string prefix.
        $siblingDir = $this->tempDir.'-secret';
        mkdir($siblingDir);
        file_put_contents($siblingDir.'/secret.txt', 'top secret');

        try {
            $resolver = new FileSystemResolver([$this->tempDir]);

            $this->expectException(PathResolutionException::class);
            $resolver->resolve('../'.basename($siblingDir).'/secret.txt');
        } finally {
            unlink($siblingDir.'/secret.txt');
            rmdir($siblingDir);
        }
    }
}
