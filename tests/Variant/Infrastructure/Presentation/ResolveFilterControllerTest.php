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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Infrastructure\Presentation;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\FakeImageManipulator;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\FakeOriginalUrlResolver;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\FakeSourceReader;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\FrozenClock;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\InMemoryGenerationLock;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\InMemoryVariantStorage;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\SpyDomainEventBus;
use Tito10047\ProgressiveImageBundle\Variant\Application\Handler\GenerateVariantHandler;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\FilterFactory;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\FilterSetRegistry;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\VariantSpecFactory;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Dimensions;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\GeneratedImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourceImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Variant;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Service\AspectCropCalculator;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Service\VariantIdHasher;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Presentation\Controller\ResolveFilterController;

final class ResolveFilterControllerTest extends TestCase
{
    private InMemoryVariantStorage $storage;
    private VariantIdHasher $hasher;
    private VariantSpecFactory $specFactory;
    private FakeSourceReader $sourceReader;

    protected function setUp(): void
    {
        $this->storage = new InMemoryVariantStorage();
        $this->hasher = new VariantIdHasher('secret');
        $this->specFactory = new VariantSpecFactory(
            new FilterSetRegistry(['thumb_small' => ['filters' => ['thumbnail' => ['size' => [100, 100], 'mode' => 'outbound']]]], new FilterFactory()),
            new FilterFactory(),
            new AspectCropCalculator()
        );
        $stream = fopen('php://memory', 'r');
        self::assertNotFalse($stream);
        $this->sourceReader = FakeSourceReader::returning(new SourceImage($stream, new Dimensions(1000, 1000), 'image/jpeg'));
    }

    private SpyDomainEventBus $eventBus;

    private function makeController(): ResolveFilterController
    {
        $this->eventBus = new SpyDomainEventBus();
        $generateHandler = new GenerateVariantHandler(
            $this->hasher,
            new InMemoryGenerationLock(),
            $this->storage,
            $this->sourceReader,
            new FakeImageManipulator(),
            [],
            $this->eventBus,
            new FrozenClock()
        );

        return new ResolveFilterController(
            $this->specFactory,
            $this->hasher,
            $this->storage,
            $generateHandler,
            new FakeOriginalUrlResolver(),
        );
    }

    public function testRedirectsToThePublicPathOnHit(): void
    {
        $spec = $this->specFactory->createFromFilterSet('thumb_small');
        $variant = Variant::request(new SourcePath('a.jpg'), $spec, $this->hasher);
        $this->storage->write($variant->path(), new GeneratedImage('bytes', $spec->format));

        $response = $this->makeController()->resolve('thumb_small', 'a.jpg');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->storage->publicPath($variant->path()), $response->headers->get('Location'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));
    }

    public function testGeneratesSynchronouslyThenRedirectsOnMiss(): void
    {
        $spec = $this->specFactory->createFromFilterSet('thumb_small');
        $variant = Variant::request(new SourcePath('a.jpg'), $spec, $this->hasher);

        $response = $this->makeController()->resolve('thumb_small', 'a.jpg');

        self::assertSame(302, $response->getStatusCode());
        self::assertTrue($this->storage->exists($variant->path()), 'the controller must generate synchronously, not just redirect blindly');
        self::assertSame($this->storage->publicPath($variant->path()), $response->headers->get('Location'));
    }

    public function testASecondRequestForTheSameVariantIsAFastCacheHit(): void
    {
        $controller = $this->makeController();

        $controller->resolve('thumb_small', 'a.jpg');
        self::assertCount(1, $this->eventBus->published(), 'the first request must have generated the variant');

        $controller->resolve('thumb_small', 'a.jpg');

        self::assertCount(1, $this->eventBus->published(), 'a second resolve of the same variant must not regenerate it');
    }

    public function testFallsBackToOriginalWithNoStoreWhenGenerationFails(): void
    {
        $this->sourceReader = FakeSourceReader::failingWith();

        $previousErrorLog = ini_get('error_log');
        $tmpFile = sys_get_temp_dir().'/pgi-error-log-'.bin2hex(random_bytes(8)).'.log';
        ini_set('error_log', $tmpFile);

        try {
            $response = $this->makeController()->resolve('thumb_small', 'a.jpg');

            self::assertSame(302, $response->getStatusCode());
            self::assertSame('/original/a.jpg', $response->headers->get('Location'));
            self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        } finally {
            ini_set('error_log', false !== $previousErrorLog ? $previousErrorLog : '');
            @unlink($tmpFile);
        }
    }
}
