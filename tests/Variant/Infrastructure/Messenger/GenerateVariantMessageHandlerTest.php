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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Infrastructure\Messenger;

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
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Messenger\GenerateVariantMessageHandler;

final class GenerateVariantMessageHandlerTest extends TestCase
{
    public function testDelegatesToTheApplicationHandler(): void
    {
        $storage = new InMemoryVariantStorage();
        $hasher = new VariantIdHasher('secret');
        $stream = fopen('php://memory', 'r');
        self::assertNotFalse($stream);

        $applicationHandler = new GenerateVariantHandler(
            $hasher,
            new InMemoryGenerationLock(),
            $storage,
            FakeSourceReader::returning(new SourceImage($stream, new Dimensions(100, 100), 'image/jpeg')),
            new FakeImageManipulator(),
            [],
            new SpyDomainEventBus(),
            new FrozenClock()
        );

        $messageHandler = new GenerateVariantMessageHandler($applicationHandler);

        $source = new SourcePath('uploads/hero.jpg');
        $spec = new VariantSpec(FilterChain::of(Thumbnail::outbound(new Dimensions(200, 200))), OutputFormat::Webp, new Quality(82));
        $messageHandler(new GenerateVariant($source, $spec));

        $variant = Variant::request($source, $spec, $hasher);
        self::assertTrue($storage->exists($variant->path()));
    }
}
