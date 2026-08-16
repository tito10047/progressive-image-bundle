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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Infrastructure\PostProcess;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Exception\VariantDomainException;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\GeneratedImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\PostProcess\JpegoptimPostProcessor;

final class JpegoptimPostProcessorTest extends TestCase
{
    private JpegoptimPostProcessor $processor;

    protected function setUp(): void
    {
        $bin = (new ExecutableFinder())->find('jpegoptim');
        if (null === $bin) {
            self::markTestSkipped('jpegoptim binary is not installed.');
        }

        $this->processor = new JpegoptimPostProcessor($bin);
    }

    public function testSupportsOnlyJpeg(): void
    {
        self::assertTrue($this->processor->supports(OutputFormat::Jpeg));
        self::assertFalse($this->processor->supports(OutputFormat::Webp));
        self::assertFalse($this->processor->supports(OutputFormat::Png));
        self::assertFalse($this->processor->supports(OutputFormat::Avif));
    }

    public function testProcessesAValidJpegImage(): void
    {
        $result = $this->processor->process(new GeneratedImage($this->sampleJpegBytes(), OutputFormat::Jpeg));

        self::assertSame(OutputFormat::Jpeg, $result->format);
        $info = getimagesizefromstring($result->contents);
        self::assertIsArray($info);
        self::assertSame('image/jpeg', $info['mime']);
    }

    public function testThrowsOnInvalidInput(): void
    {
        $this->expectException(VariantDomainException::class);

        $this->processor->process(new GeneratedImage('not a real image', OutputFormat::Jpeg));
    }

    private function sampleJpegBytes(): string
    {
        $image = imagecreatetruecolor(20, 20);
        ob_start();
        imagejpeg($image);
        $bytes = ob_get_clean();
        self::assertIsString($bytes);

        return $bytes;
    }
}
