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
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\SpyMessageBus;
use Tito10047\ProgressiveImageBundle\Variant\Application\Command\GenerateVariant;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\FilterChain;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Thumbnail;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Dimensions;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Quality;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantSpec;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Service\VariantIdHasher;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Messenger\MessengerGenerationDispatcher;

final class MessengerGenerationDispatcherTest extends TestCase
{
    private SpyMessageBus $bus;
    private MessengerGenerationDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->bus = new SpyMessageBus();
        $this->dispatcher = new MessengerGenerationDispatcher($this->bus, new VariantIdHasher('secret'));
    }

    private function command(string $path): GenerateVariant
    {
        return new GenerateVariant(
            new SourcePath($path),
            new VariantSpec(FilterChain::of(Thumbnail::outbound(new Dimensions(200, 200))), OutputFormat::Webp, new Quality(82))
        );
    }

    public function testDispatchPutsTheCommandOnTheBus(): void
    {
        $this->dispatcher->dispatch($this->command('uploads/hero.jpg'));

        self::assertCount(1, $this->bus->dispatched());
        self::assertInstanceOf(GenerateVariant::class, $this->bus->dispatched()[0]);
    }

    public function testDoesNotDispatchTheSameVariantTwiceInOneRequest(): void
    {
        $this->dispatcher->dispatch($this->command('uploads/hero.jpg'));
        $this->dispatcher->dispatch($this->command('uploads/hero.jpg'));

        self::assertCount(1, $this->bus->dispatched(), 'the same (source, spec) hashes to the same VariantId — must dispatch once per request');
    }

    public function testDispatchesDifferentVariantsSeparately(): void
    {
        $this->dispatcher->dispatch($this->command('uploads/a.jpg'));
        $this->dispatcher->dispatch($this->command('uploads/b.jpg'));

        self::assertCount(2, $this->bus->dispatched());
    }
}
