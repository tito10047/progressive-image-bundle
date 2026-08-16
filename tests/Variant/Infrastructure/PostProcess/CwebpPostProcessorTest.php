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
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\PostProcess\CwebpPostProcessor;

final class CwebpPostProcessorTest extends TestCase
{
    private CwebpPostProcessor $processor;

    protected function setUp(): void
    {
        $bin = (new ExecutableFinder())->find('cwebp');
        if (null === $bin) {
            self::markTestSkipped('cwebp binary is not installed.');
        }

        $this->processor = new CwebpPostProcessor($bin);
    }

    public function testSupportsOnlyWebp(): void
    {
        self::assertTrue($this->processor->supports(OutputFormat::Webp));
        self::assertFalse($this->processor->supports(OutputFormat::Jpeg));
        self::assertFalse($this->processor->supports(OutputFormat::Png));
        self::assertFalse($this->processor->supports(OutputFormat::Avif));
    }

    public function testProcessesAValidWebpImage(): void
    {
        $webp = $this->sampleWebpBytes();

        $result = $this->processor->process(new GeneratedImage($webp, OutputFormat::Webp));

        self::assertSame(OutputFormat::Webp, $result->format);
        self::assertNotSame('', $result->contents);
        $info = getimagesizefromstring($result->contents);
        self::assertIsArray($info);
        self::assertSame('image/webp', $info['mime']);
    }

    public function testThrowsOnInvalidInput(): void
    {
        $this->expectException(VariantDomainException::class);

        $this->processor->process(new GeneratedImage('not a real image', OutputFormat::Webp));
    }

    public function testConfiguredQualityIsPassedToTheCliBinaryInsteadOfItsOwnDefault(): void
    {
        $bin = (new ExecutableFinder())->find('cwebp');
        self::assertNotNull($bin);

        $noisy = $this->sampleNoisyWebpBytes();

        $lowQuality = (new CwebpPostProcessor($bin, quality: 5))->process(new GeneratedImage($noisy, OutputFormat::Webp));
        $highQuality = (new CwebpPostProcessor($bin, quality: 95))->process(new GeneratedImage($noisy, OutputFormat::Webp));

        self::assertLessThan(\strlen($highQuality->contents), \strlen($lowQuality->contents), 'a low -q setting must produce meaningfully smaller output than a high one for the same noisy input');
    }

    private function sampleWebpBytes(): string
    {
        $image = imagecreatetruecolor(20, 20);
        ob_start();
        imagewebp($image);
        $bytes = ob_get_clean();
        self::assertIsString($bytes);

        return $bytes;
    }

    private function sampleNoisyWebpBytes(): string
    {
        $image = imagecreatetruecolor(200, 200);
        mt_srand(42);
        for ($x = 0; $x < 200; ++$x) {
            for ($y = 0; $y < 200; ++$y) {
                imagesetpixel($image, $x, $y, imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
            }
        }
        ob_start();
        imagewebp($image, quality: 100);
        $bytes = ob_get_clean();
        self::assertIsString($bytes);

        return $bytes;
    }
}
