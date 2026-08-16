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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Infrastructure\Source;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Loader\FileSystemLoader;
use Tito10047\ProgressiveImageBundle\Resolver\FileSystemResolver;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Exception\SourceNotReadable;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Source\ResolverChainSourceReader;

final class ResolverChainSourceReaderTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/pgi-source-reader-'.bin2hex(random_bytes(8));
        mkdir($this->root);

        $image = imagecreatetruecolor(120, 80);
        imagepng($image, $this->root.'/hero.png');

        file_put_contents($this->root.'/not-an-image.txt', 'hello');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->root);
    }

    private function makeReader(): ResolverChainSourceReader
    {
        return new ResolverChainSourceReader(new FileSystemResolver([$this->root]), new FileSystemLoader());
    }

    public function testReadsDimensionsMimeAndAStreamableSource(): void
    {
        $reader = $this->makeReader();
        $image = $reader->read(new SourcePath('hero.png'));

        self::assertSame(120, $image->dimensions->width);
        self::assertSame(80, $image->dimensions->height);
        self::assertSame('image/png', $image->mime);
        self::assertNotFalse(stream_get_contents($image->stream));
    }

    public function testThrowsSourceNotReadableWhenPathCannotBeResolved(): void
    {
        $this->expectException(SourceNotReadable::class);

        $this->makeReader()->read(new SourcePath('missing.png'));
    }

    public function testThrowsSourceNotReadableWhenFileIsNotAnImage(): void
    {
        $this->expectException(SourceNotReadable::class);

        $this->makeReader()->read(new SourcePath('not-an-image.txt'));
    }
}
