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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\FakeImageManipulator;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\FakeOriginalUrlResolver;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\FakeSourceReader;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\FakeUrlSigner;
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
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Presentation\Controller\ImageVariantController;

final class ImageVariantControllerTest extends TestCase
{
    private InMemoryVariantStorage $storage;
    private VariantIdHasher $hasher;
    private VariantSpecFactory $specFactory;
    private FakeUrlSigner $signer;
    private FakeSourceReader $sourceReader;

    protected function setUp(): void
    {
        $this->storage = new InMemoryVariantStorage();
        $this->hasher = new VariantIdHasher('secret');
        $this->specFactory = new VariantSpecFactory(new FilterSetRegistry([], new FilterFactory()), new FilterFactory(), new AspectCropCalculator());
        $this->signer = new FakeUrlSigner();
        $stream = fopen('php://memory', 'r');
        self::assertNotFalse($stream);
        $this->sourceReader = FakeSourceReader::returning(new SourceImage($stream, new Dimensions(1000, 1000), 'image/jpeg'));
    }

    private function makeController(): ImageVariantController
    {
        $generateHandler = new GenerateVariantHandler(
            $this->hasher,
            new InMemoryGenerationLock(),
            $this->storage,
            $this->sourceReader,
            new FakeImageManipulator(),
            [],
            new SpyDomainEventBus(),
            new FrozenClock()
        );

        return new ImageVariantController(
            $this->specFactory,
            $this->hasher,
            $this->storage,
            $generateHandler,
            new FakeOriginalUrlResolver(),
            $this->signer
        );
    }

    public function testRejectsAnUnsignedRequest(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->makeController()->serve(Request::create('https://example.com/pgi_variant_serve?source=a.jpg&width=200&height=200'), 'a.jpg', 200, 200);
    }

    public function testRedirectsToThePublicPathOnHit(): void
    {
        $spec = $this->specFactory->create(200, 200);
        $variant = Variant::request(new SourcePath('a.jpg'), $spec, $this->hasher);
        $this->storage->write($variant->path(), new GeneratedImage('bytes', $spec->format));

        $request = Request::create($this->signer->sign('https://example.com/pgi_variant_serve'));
        $response = $this->makeController()->serve($request, 'a.jpg', 200, 200);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->storage->publicPath($variant->path()), $response->headers->get('Location'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));
    }

    public function testGeneratesSynchronouslyThenRedirectsOnMiss(): void
    {
        $spec = $this->specFactory->create(200, 200);
        $variant = Variant::request(new SourcePath('a.jpg'), $spec, $this->hasher);

        $request = Request::create($this->signer->sign('https://example.com/pgi_variant_serve'));
        $response = $this->makeController()->serve($request, 'a.jpg', 200, 200);

        self::assertSame(302, $response->getStatusCode());
        self::assertTrue($this->storage->exists($variant->path()), 'the controller must generate synchronously, not just redirect blindly');
        self::assertSame($this->storage->publicPath($variant->path()), $response->headers->get('Location'));
    }

    public function testFallsBackToOriginalWithNoStoreWhenGenerationFails(): void
    {
        $this->sourceReader = FakeSourceReader::failingWith();

        // makeController() never injects a logger, so the controller's failure path falls
        // back to error_log(); redirect it so that output doesn't leak to stdout and make
        // this test "risky".
        $previousErrorLog = ini_get('error_log');
        $tmpFile = sys_get_temp_dir().'/pgi-error-log-'.bin2hex(random_bytes(8)).'.log';
        ini_set('error_log', $tmpFile);

        try {
            $request = Request::create($this->signer->sign('https://example.com/pgi_variant_serve'));
            $response = $this->makeController()->serve($request, 'a.jpg', 200, 200);

            self::assertSame(302, $response->getStatusCode());
            self::assertSame('/original/a.jpg', $response->headers->get('Location'));
            self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
            self::assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));
        } finally {
            ini_set('error_log', false !== $previousErrorLog ? $previousErrorLog : '');
            @unlink($tmpFile);
        }
    }

    public function testLogsToErrorLogWhenGenerationFailsAndNoLoggerServiceIsAvailable(): void
    {
        $this->sourceReader = FakeSourceReader::failingWith();

        $previousErrorLog = ini_get('error_log');
        $tmpFile = sys_get_temp_dir().'/pgi-error-log-'.bin2hex(random_bytes(8)).'.log';
        ini_set('error_log', $tmpFile);

        try {
            $request = Request::create($this->signer->sign('https://example.com/pgi_variant_serve'));
            // makeController() never injects a logger, so $this->logger is null — the
            // controller must still surface the failure somewhere instead of swallowing it.
            $this->makeController()->serve($request, 'a.jpg', 200, 200);

            self::assertFileExists($tmpFile);
            self::assertStringContainsString('Synchronous variant generation failed', (string) file_get_contents($tmpFile));
        } finally {
            ini_set('error_log', false !== $previousErrorLog ? $previousErrorLog : '');
            @unlink($tmpFile);
        }
    }

    public function testRebuildsTheSameVariantIdFromFilterSetPoiAndContext(): void
    {
        $specFactory = new VariantSpecFactory(
            new FilterSetRegistry(['sq' => ['filters' => []]], new FilterFactory()),
            new FilterFactory(),
            new AspectCropCalculator()
        );
        $expectedSpec = $specFactory->create(100, 100, 'sq', null, null, ['quality' => 42]);
        $expectedVariant = Variant::request(new SourcePath('a.jpg'), $expectedSpec, $this->hasher);
        $this->storage->write($expectedVariant->path(), new GeneratedImage('bytes', $expectedSpec->format));

        $generateHandler = new GenerateVariantHandler($this->hasher, new InMemoryGenerationLock(), $this->storage, $this->sourceReader, new FakeImageManipulator(), [], new SpyDomainEventBus(), new FrozenClock());
        $controller = new ImageVariantController($specFactory, $this->hasher, $this->storage, $generateHandler, new FakeOriginalUrlResolver(), $this->signer);

        $request = Request::create($this->signer->sign('https://example.com/pgi_variant_serve'));
        $response = $controller->serve($request, 'a.jpg', 100, 100, 'sq', null, null, null, null, '{"quality":42}');

        self::assertSame($this->storage->publicPath($expectedVariant->path()), $response->headers->get('Location'));
    }
}
