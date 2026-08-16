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

namespace Tito10047\ProgressiveImageBundle\Variant\Application\Handler;

use Tito10047\ProgressiveImageBundle\Variant\Application\Command\GenerateVariant;
use Tito10047\ProgressiveImageBundle\Variant\Application\Port\DomainEventBus;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Variant;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantPath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\Clock;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\GenerationLock;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\ImageManipulator;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\PostProcessor;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\SourceReader;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\VariantStorage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Service\VariantIdHasher;

/**
 * The single generation code path — Messenger, kernel.terminate and sync dispatch
 * strategies all call this same handler, so the load/manipulate/store logic exists once.
 */
final readonly class GenerateVariantHandler
{
    /**
     * @param iterable<PostProcessor> $postProcessors
     */
    public function __construct(
        private VariantIdHasher $hasher,
        private GenerationLock $lock,
        private VariantStorage $storage,
        private SourceReader $sourceReader,
        private ImageManipulator $manipulator,
        private iterable $postProcessors,
        private DomainEventBus $eventBus,
        private Clock $clock,
        private int $failMarkerTtlSeconds = 300,
    ) {
    }

    public function __invoke(GenerateVariant $command): void
    {
        $variant = Variant::request($command->source, $command->spec, $this->hasher);
        $path = $variant->path();

        $lock = $this->lock->acquire($variant->id);
        if (null === $lock) {
            return;
        }

        try {
            if ($this->storage->exists($path)) {
                return;
            }

            if ($this->hasFreshFailMarker($path)) {
                return;
            }

            $variant->startGenerating();

            try {
                $source = $this->sourceReader->read($variant->source);
                $image = $this->manipulator->process($source, $variant->spec);

                foreach ($this->postProcessors as $postProcessor) {
                    if ($postProcessor->supports($variant->spec->format)) {
                        $image = $postProcessor->process($image);
                    }
                }

                $this->storage->write($path, $image);
                $this->eventBus->publish($variant->markReady());
            } catch (\Throwable $e) {
                $this->storage->writeFailMarker($path, $this->clock->now());
                $this->eventBus->publish($variant->markFailed($e));

                throw $e;
            }
        } finally {
            $this->lock->release($lock);
        }
    }

    private function hasFreshFailMarker(VariantPath $path): bool
    {
        $failedAt = $this->storage->failMarkerTimestamp($path);
        if (null === $failedAt) {
            return false;
        }

        return $this->clock->now()->getTimestamp() - $failedAt->getTimestamp() < $this->failMarkerTtlSeconds;
    }
}
