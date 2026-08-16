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
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\FakeSourceReader;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Dimensions;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourceImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Source\ChainSourceReader;

final class ChainSourceReaderTest extends TestCase
{
    private function stubImage(string $marker): SourceImage
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $marker);
        rewind($stream);

        return new SourceImage($stream, new Dimensions(10, 10), 'image/png');
    }

    public function testRoutesHttpSourcesToTheRemoteReader(): void
    {
        $remote = FakeSourceReader::returning($this->stubImage('remote'));
        $local = FakeSourceReader::failingWith();

        $reader = new ChainSourceReader($local, $remote);
        $image = $reader->read(new SourcePath('https://images.example.com/hero.png'));

        self::assertSame('remote', stream_get_contents($image->stream));
    }

    public function testRoutesHttpsSourcesToTheRemoteReaderCaseInsensitively(): void
    {
        $remote = FakeSourceReader::returning($this->stubImage('remote'));
        $local = FakeSourceReader::failingWith();

        $reader = new ChainSourceReader($local, $remote);
        $image = $reader->read(new SourcePath('HTTPS://images.example.com/hero.png'));

        self::assertSame('remote', stream_get_contents($image->stream));
    }

    public function testRoutesPlainPathsToTheLocalReader(): void
    {
        $remote = FakeSourceReader::failingWith();
        $local = FakeSourceReader::returning($this->stubImage('local'));

        $reader = new ChainSourceReader($local, $remote);
        $image = $reader->read(new SourcePath('uploads/hero.png'));

        self::assertSame('local', stream_get_contents($image->stream));
    }
}
