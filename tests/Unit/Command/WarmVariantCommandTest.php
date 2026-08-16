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

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tito10047\ProgressiveImageBundle\Command\WarmVariantCommand;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\FakeImageManipulator;
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

final class WarmVariantCommandTest extends TestCase
{
    private InMemoryVariantStorage $storage;
    private VariantIdHasher $hasher;
    private VariantSpecFactory $specFactory;
    private FilterSetRegistry $filterSets;
    private FakeSourceReader $sourceReader;

    protected function setUp(): void
    {
        $this->storage = new InMemoryVariantStorage();
        $this->hasher = new VariantIdHasher('secret');
        $this->filterSets = new FilterSetRegistry([
            'thumb_small' => ['filters' => ['thumbnail' => ['size' => [100, 100], 'mode' => 'outbound']]],
            'thumb_large' => ['filters' => ['thumbnail' => ['size' => [400, 400], 'mode' => 'outbound']]],
        ], new FilterFactory());
        $this->specFactory = new VariantSpecFactory($this->filterSets, new FilterFactory(), new AspectCropCalculator());

        $stream = fopen('php://memory', 'r');
        self::assertNotFalse($stream);
        $this->sourceReader = FakeSourceReader::returning(new SourceImage($stream, new Dimensions(1000, 1000), 'image/jpeg'));
    }

    private function makeCommand(): WarmVariantCommand
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

        return new WarmVariantCommand($this->specFactory, $this->hasher, $this->storage, $generateHandler, $this->filterSets);
    }

    public function testGeneratesTheGivenFilterSet(): void
    {
        $tester = new CommandTester($this->makeCommand());

        $exitCode = $tester->execute(['source' => 'uploads/hero.jpg', '--filter-set' => ['thumb_small']]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $spec = $this->specFactory->createFromFilterSet('thumb_small');
        $variant = Variant::request(new SourcePath('uploads/hero.jpg'), $spec, $this->hasher);
        self::assertTrue($this->storage->exists($variant->path()));
        self::assertStringContainsString('generated', $tester->getDisplay());
    }

    public function testWarmsEveryConfiguredFilterSetWhenNoneIsSpecified(): void
    {
        $tester = new CommandTester($this->makeCommand());

        $tester->execute(['source' => 'uploads/hero.jpg']);

        foreach (['thumb_small', 'thumb_large'] as $name) {
            $spec = $this->specFactory->createFromFilterSet($name);
            $variant = Variant::request(new SourcePath('uploads/hero.jpg'), $spec, $this->hasher);
            self::assertTrue($this->storage->exists($variant->path()), "expected $name to have been warmed");
        }
    }

    public function testSkipsFilterSetsThatAlreadyHaveAVariantStored(): void
    {
        $spec = $this->specFactory->createFromFilterSet('thumb_small');
        $variant = Variant::request(new SourcePath('uploads/hero.jpg'), $spec, $this->hasher);
        $this->storage->write($variant->path(), new GeneratedImage('bytes', $spec->format));

        // A source reader that fails would blow up any attempt to actually (re)generate —
        // if the command still reports success, it must genuinely have skipped generation.
        $this->sourceReader = FakeSourceReader::failingWith();
        $tester = new CommandTester($this->makeCommand());

        $exitCode = $tester->execute(['source' => 'uploads/hero.jpg', '--filter-set' => ['thumb_small']]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('already exists', $tester->getDisplay());
    }

    public function testReportsFailureExitCodeWhenGenerationFails(): void
    {
        $this->sourceReader = FakeSourceReader::failingWith();
        $tester = new CommandTester($this->makeCommand());

        $exitCode = $tester->execute(['source' => 'uploads/hero.jpg', '--filter-set' => ['thumb_small']]);

        self::assertSame(Command::FAILURE, $exitCode);
    }
}
