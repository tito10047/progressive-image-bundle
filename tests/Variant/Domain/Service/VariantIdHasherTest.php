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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Domain\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Crop;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\FilterChain;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Thumbnail;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\CropBox;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Dimensions;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Quality;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantSpec;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Service\VariantIdHasher;

/**
 * Golden test: these hashes were computed once against a fixed secret and are frozen here.
 * A failing assertion means the hash-schema contract changed (canonical() shape, HMAC
 * algorithm, base64url encoding, ...) — that invalidates every VariantId ever issued and
 * requires a deliberate hash-schema version bump, not a quiet fix.
 *
 * Regenerated for HASH_SCHEMA_VERSION 2 (VariantSpec::canonical() gained "progressive"/
 * "strip_metadata" keys) — the previous v1 literals are preserved in git history.
 */
final class VariantIdHasherTest extends TestCase
{
    private const string SECRET = 'test-secret';

    #[DataProvider('goldenHashProvider')]
    public function testProducesStableGoldenHash(SourcePath $source, VariantSpec $spec, string $expectedHash): void
    {
        $hasher = new VariantIdHasher(self::SECRET);

        self::assertSame($expectedHash, $hasher->hash($source, $spec)->value);
    }

    /**
     * @return iterable<string, array{SourcePath, VariantSpec, string}>
     */
    public static function goldenHashProvider(): iterable
    {
        yield 'simple thumbnail' => [
            new SourcePath('uploads/hero.jpg'),
            new VariantSpec(FilterChain::of(Thumbnail::outbound(new Dimensions(200, 200))), OutputFormat::Webp, new Quality(82)),
            'I53U4OsoKYYSXAuex0Un_fnhli9QxVzRKLJBpjBBZ_M',
        ];

        yield 'crop then thumbnail' => [
            new SourcePath('uploads/hero.jpg'),
            new VariantSpec(
                FilterChain::of(
                    new Crop(new CropBox(100, 0, new Dimensions(100, 100))),
                    Thumbnail::inset(new Dimensions(100, 100))
                ),
                OutputFormat::Jpeg,
                new Quality(85)
            ),
            'YXXtKl2hEocodkMXA3Rs5siqjpOVX38CHxFuyzu3HIU',
        ];

        yield 'empty filters avif' => [
            new SourcePath('images/logo.png'),
            new VariantSpec(FilterChain::empty(), OutputFormat::Avif, new Quality(60)),
            'o91Q021BiM5YKOtPY0t55UMdMk_EGUqZO0icu-u5pv0',
        ];
    }

    public function testSameInputsProduceSameHash(): void
    {
        $hasher = new VariantIdHasher(self::SECRET);
        $source = new SourcePath('uploads/hero.jpg');
        $spec = new VariantSpec(FilterChain::of(Thumbnail::outbound(new Dimensions(200, 200))), OutputFormat::Webp, new Quality(82));

        self::assertTrue($hasher->hash($source, $spec)->equals($hasher->hash($source, $spec)));
    }

    public function testDifferentSourcePathsProduceDifferentHashes(): void
    {
        $hasher = new VariantIdHasher(self::SECRET);
        $spec = new VariantSpec(FilterChain::of(Thumbnail::outbound(new Dimensions(200, 200))), OutputFormat::Webp, new Quality(82));

        $a = $hasher->hash(new SourcePath('uploads/hero.jpg'), $spec);
        $b = $hasher->hash(new SourcePath('uploads/other.jpg'), $spec);

        self::assertFalse($a->equals($b));
    }

    public function testDifferentSecretsProduceDifferentHashes(): void
    {
        $source = new SourcePath('uploads/hero.jpg');
        $spec = new VariantSpec(FilterChain::of(Thumbnail::outbound(new Dimensions(200, 200))), OutputFormat::Webp, new Quality(82));

        $a = (new VariantIdHasher('secret-a'))->hash($source, $spec);
        $b = (new VariantIdHasher('secret-b'))->hash($source, $spec);

        self::assertFalse($a->equals($b));
    }
}
