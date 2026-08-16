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
use Tito10047\ProgressiveImageBundle\Command\RemoveVariantCommand;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\InMemoryVariantStorage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\GeneratedImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantId;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantPath;

final class RemoveVariantCommandTest extends TestCase
{
    private InMemoryVariantStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new InMemoryVariantStorage();
    }

    private function makeCommand(): RemoveVariantCommand
    {
        return new RemoveVariantCommand($this->storage);
    }

    public function testDeletesEveryVariantStoredForTheSource(): void
    {
        $source = new SourcePath('uploads/hero.jpg');
        $jpeg = VariantPath::for(new VariantId('aaaa0000'), $source, OutputFormat::Jpeg);
        $webp = VariantPath::for(new VariantId('bbbb1111'), $source, OutputFormat::Webp);
        $this->storage->write($jpeg, new GeneratedImage('a', OutputFormat::Jpeg));
        $this->storage->write($webp, new GeneratedImage('b', OutputFormat::Webp));

        $tester = new CommandTester($this->makeCommand());
        $exitCode = $tester->execute(['source' => 'uploads/hero.jpg']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertFalse($this->storage->exists($jpeg));
        self::assertFalse($this->storage->exists($webp));
    }

    public function testDoesNotTouchVariantsBelongingToOtherSources(): void
    {
        $other = VariantPath::for(new VariantId('cccc2222'), new SourcePath('uploads/other.jpg'), OutputFormat::Jpeg);
        $this->storage->write($other, new GeneratedImage('c', OutputFormat::Jpeg));

        $tester = new CommandTester($this->makeCommand());
        $tester->execute(['source' => 'uploads/hero.jpg']);

        self::assertTrue($this->storage->exists($other));
    }

    public function testDryRunListsWithoutDeleting(): void
    {
        $source = new SourcePath('uploads/hero.jpg');
        $jpeg = VariantPath::for(new VariantId('aaaa0000'), $source, OutputFormat::Jpeg);
        $this->storage->write($jpeg, new GeneratedImage('a', OutputFormat::Jpeg));

        $tester = new CommandTester($this->makeCommand());
        $tester->execute(['source' => 'uploads/hero.jpg', '--dry-run' => true]);

        self::assertTrue($this->storage->exists($jpeg), 'dry-run must not delete anything');
        self::assertStringContainsString('would delete', $tester->getDisplay());
    }

    public function testSucceedsWithAClearMessageWhenNothingIsStoredForTheSource(): void
    {
        $tester = new CommandTester($this->makeCommand());

        $exitCode = $tester->execute(['source' => 'uploads/does-not-exist.jpg']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('No stored variants found', $tester->getDisplay());
    }
}
