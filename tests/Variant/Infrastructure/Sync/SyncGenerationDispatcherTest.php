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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Infrastructure\Sync;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\FakeImageManipulator;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\FakeSourceReader;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\FrozenClock;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\InMemoryGenerationLock;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\InMemoryVariantStorage;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\SpyDomainEventBus;
use Tito10047\ProgressiveImageBundle\Variant\Application\Command\GenerateVariant;
use Tito10047\ProgressiveImageBundle\Variant\Application\Handler\GenerateVariantHandler;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\FilterChain;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Thumbnail;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Dimensions;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Quality;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourceImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Variant;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantSpec;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Service\VariantIdHasher;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Sync\SyncGenerationDispatcher;

final class SyncGenerationDispatcherTest extends TestCase
{
    public function testDispatchRunsGenerationImmediately(): void
    {
        $storage = new InMemoryVariantStorage();
        $hasher = new VariantIdHasher('secret');
        $stream = fopen('php://memory', 'r');
        self::assertNotFalse($stream);

        $handler = new GenerateVariantHandler(
            $hasher,
            new InMemoryGenerationLock(),
            $storage,
            FakeSourceReader::returning(new SourceImage($stream, new Dimensions(100, 100), 'image/jpeg')),
            new FakeImageManipulator(),
            [],
            new SpyDomainEventBus(),
            new FrozenClock()
        );

        $dispatcher = new SyncGenerationDispatcher($handler);

        $source = new SourcePath('uploads/hero.jpg');
        $spec = new VariantSpec(FilterChain::of(Thumbnail::outbound(new Dimensions(200, 200))), OutputFormat::Webp, new Quality(82));
        $dispatcher->dispatch(new GenerateVariant($source, $spec));

        $variant = Variant::request($source, $spec, $hasher);
        self::assertTrue($storage->exists($variant->path()), 'dispatch() must run generation synchronously, not queue it');
    }

    /**
     * ResolveVariantUrlHandler calls dispatch() with no try/catch of its own, relying on
     * PendingFallbackStrategy (original image / wait) to handle a still-pending variant. If
     * dispatch() let a generation failure escape as an exception, the "sync" strategy would
     * be the only one where a broken source crashes the whole page render (500) instead of
     * falling back — async/terminate structurally can't do that, since they never run
     * generation inline.
     */
    public function testDispatchDoesNotLetAGenerationFailureEscapeAsAnExceptionAndLogsIt(): void
    {
        $storage = new InMemoryVariantStorage();
        $hasher = new VariantIdHasher('secret');

        $handler = new GenerateVariantHandler(
            $hasher,
            new InMemoryGenerationLock(),
            $storage,
            FakeSourceReader::failingWith(),
            new FakeImageManipulator(),
            [],
            new SpyDomainEventBus(),
            new FrozenClock()
        );

        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $dispatcher = new SyncGenerationDispatcher($handler, $logger);

        $source = new SourcePath('uploads/broken.jpg');
        $spec = new VariantSpec(FilterChain::of(Thumbnail::outbound(new Dimensions(200, 200))), OutputFormat::Webp, new Quality(82));

        $dispatcher->dispatch(new GenerateVariant($source, $spec));

        $variant = Variant::request($source, $spec, $hasher);
        self::assertFalse($storage->exists($variant->path()));
    }

    public function testDispatchLogsToErrorLogWhenGenerationFailsAndNoLoggerServiceIsAvailable(): void
    {
        $handler = new GenerateVariantHandler(
            new VariantIdHasher('secret'),
            new InMemoryGenerationLock(),
            new InMemoryVariantStorage(),
            FakeSourceReader::failingWith(),
            new FakeImageManipulator(),
            [],
            new SpyDomainEventBus(),
            new FrozenClock()
        );

        $previousErrorLog = ini_get('error_log');
        $tmpFile = sys_get_temp_dir().'/pgi-error-log-'.bin2hex(random_bytes(8)).'.log';
        ini_set('error_log', $tmpFile);

        try {
            $dispatcher = new SyncGenerationDispatcher($handler);
            $spec = new VariantSpec(FilterChain::of(Thumbnail::outbound(new Dimensions(200, 200))), OutputFormat::Webp, new Quality(82));
            $dispatcher->dispatch(new GenerateVariant(new SourcePath('uploads/broken.jpg'), $spec));

            self::assertFileExists($tmpFile);
            self::assertStringContainsString('Synchronous variant generation failed', (string) file_get_contents($tmpFile));
        } finally {
            ini_set('error_log', false !== $previousErrorLog ? $previousErrorLog : '');
            @unlink($tmpFile);
        }
    }
}
