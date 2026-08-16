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
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\PostProcess\AvifencPostProcessor;

final class AvifencPostProcessorTest extends TestCase
{
    private AvifencPostProcessor $processor;

    protected function setUp(): void
    {
        $bin = (new ExecutableFinder())->find('avifenc');
        if (null === $bin) {
            self::markTestSkipped('avifenc binary is not installed.');
        }

        $this->processor = new AvifencPostProcessor($bin);
    }

    public function testSupportsOnlyAvif(): void
    {
        self::assertTrue($this->processor->supports(OutputFormat::Avif));
        self::assertFalse($this->processor->supports(OutputFormat::Webp));
        self::assertFalse($this->processor->supports(OutputFormat::Jpeg));
        self::assertFalse($this->processor->supports(OutputFormat::Png));
    }

    public function testReencodesAnAlreadyAvifEncodedImage(): void
    {
        $result = $this->processor->process(new GeneratedImage($this->sampleAvifBytes(), OutputFormat::Avif));

        self::assertSame(OutputFormat::Avif, $result->format);
        self::assertNotSame('', $result->contents);
        $info = getimagesizefromstring($result->contents);
        self::assertIsArray($info);
        self::assertSame('image/avif', $info['mime']);
    }

    public function testThrowsOnInvalidInput(): void
    {
        $this->expectException(VariantDomainException::class);

        $this->processor->process(new GeneratedImage('not a real image', OutputFormat::Avif));
    }

    public function testConfiguredQualityIsPassedToTheCliBinaryInsteadOfItsOwnDefault(): void
    {
        $bin = (new ExecutableFinder())->find('avifenc');
        self::assertNotNull($bin);

        $noisy = $this->sampleNoisyAvifBytes();

        $lowQuality = (new AvifencPostProcessor($bin, quality: 5))->process(new GeneratedImage($noisy, OutputFormat::Avif));
        $highQuality = (new AvifencPostProcessor($bin, quality: 95))->process(new GeneratedImage($noisy, OutputFormat::Avif));

        self::assertLessThan(\strlen($highQuality->contents), \strlen($lowQuality->contents), 'a low -q setting must produce meaningfully smaller output than a high one for the same noisy input');
    }

    private function sampleAvifBytes(): string
    {
        $image = imagecreatetruecolor(20, 20);
        $path = sys_get_temp_dir().'/pgi-avif-sample-'.bin2hex(random_bytes(4)).'.avif';
        imageavif($image, $path);
        $bytes = file_get_contents($path);
        unlink($path);
        self::assertIsString($bytes);

        return $bytes;
    }

    private function sampleNoisyAvifBytes(): string
    {
        $image = imagecreatetruecolor(200, 200);
        mt_srand(42);
        for ($x = 0; $x < 200; ++$x) {
            for ($y = 0; $y < 200; ++$y) {
                imagesetpixel($image, $x, $y, imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
            }
        }
        $path = sys_get_temp_dir().'/pgi-avif-sample-'.bin2hex(random_bytes(4)).'.avif';
        imageavif($image, $path, 100);
        $bytes = file_get_contents($path);
        unlink($path);
        self::assertIsString($bytes);

        return $bytes;
    }
}
