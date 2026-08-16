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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Domain\Model;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Event\VariantGenerated;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Event\VariantGenerationFailed;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Exception\VariantDomainException;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\FilterChain;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Thumbnail;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Dimensions;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Quality;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Variant;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantPath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantSpec;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantState;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Service\VariantIdHasher;

final class VariantTest extends TestCase
{
    private function makeSpec(): VariantSpec
    {
        return new VariantSpec(FilterChain::of(Thumbnail::outbound(new Dimensions(200, 200))), OutputFormat::Webp, new Quality(82));
    }

    public function testRequestComputesIdFromHasherAndStartsInRequestedState(): void
    {
        $hasher = new VariantIdHasher('secret');
        $source = new SourcePath('uploads/hero.jpg');
        $spec = $this->makeSpec();

        $variant = Variant::request($source, $spec, $hasher);

        self::assertTrue($variant->id->equals($hasher->hash($source, $spec)));
        self::assertSame($source, $variant->source);
        self::assertSame($spec, $variant->spec);
        self::assertSame(VariantState::Requested, $variant->state());
    }

    public function testPathIsBuiltFromIdSourceAndSpecFormat(): void
    {
        $hasher = new VariantIdHasher('secret');
        $source = new SourcePath('uploads/hero.jpg');
        $spec = $this->makeSpec();

        $variant = Variant::request($source, $spec, $hasher);

        self::assertEquals(VariantPath::for($variant->id, $source, $spec->format), $variant->path());
    }

    public function testStartGeneratingTransitionsToGenerating(): void
    {
        $variant = Variant::request(new SourcePath('a.jpg'), $this->makeSpec(), new VariantIdHasher('secret'));

        $variant->startGenerating();

        self::assertSame(VariantState::Generating, $variant->state());
    }

    public function testStartGeneratingIsAllowedAgainAfterFailure(): void
    {
        $variant = Variant::request(new SourcePath('a.jpg'), $this->makeSpec(), new VariantIdHasher('secret'));
        $variant->startGenerating();
        $variant->markFailed(new \RuntimeException('boom'));

        $variant->startGenerating();

        self::assertSame(VariantState::Generating, $variant->state());
    }

    public function testStartGeneratingThrowsWhenAlreadyReady(): void
    {
        $variant = Variant::request(new SourcePath('a.jpg'), $this->makeSpec(), new VariantIdHasher('secret'));
        $variant->startGenerating();
        $variant->markReady();

        $this->expectException(VariantDomainException::class);

        $variant->startGenerating();
    }

    public function testMarkReadyTransitionsToReadyAndReturnsEvent(): void
    {
        $variant = Variant::request(new SourcePath('a.jpg'), $this->makeSpec(), new VariantIdHasher('secret'));
        $variant->startGenerating();

        $event = $variant->markReady();

        self::assertSame(VariantState::Ready, $variant->state());
        self::assertInstanceOf(VariantGenerated::class, $event);
        self::assertTrue($event->id->equals($variant->id));
    }

    public function testMarkReadyThrowsWhenNotGenerating(): void
    {
        $variant = Variant::request(new SourcePath('a.jpg'), $this->makeSpec(), new VariantIdHasher('secret'));

        $this->expectException(VariantDomainException::class);

        $variant->markReady();
    }

    public function testMarkFailedTransitionsToFailedAndReturnsEventWithCause(): void
    {
        $variant = Variant::request(new SourcePath('a.jpg'), $this->makeSpec(), new VariantIdHasher('secret'));
        $variant->startGenerating();
        $cause = new \RuntimeException('source unreadable');

        $event = $variant->markFailed($cause);

        self::assertSame(VariantState::Failed, $variant->state());
        self::assertInstanceOf(VariantGenerationFailed::class, $event);
        self::assertTrue($event->id->equals($variant->id));
        self::assertSame($cause, $event->cause);
    }

    public function testMarkFailedThrowsWhenNotGenerating(): void
    {
        $variant = Variant::request(new SourcePath('a.jpg'), $this->makeSpec(), new VariantIdHasher('secret'));

        $this->expectException(VariantDomainException::class);

        $variant->markFailed(new \RuntimeException('boom'));
    }
}
