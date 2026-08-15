<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Tito10047\ProgressiveImageBundle\Command\GenerateCustomCssCommand;

class GenerateCustomCssCommandTest extends TestCase
{
    private const GRID_CONFIG = [
        'columns' => 12,
        'layouts' => [
            'sm' => ['min_viewport' => 640, 'max_container' => 640],
            'md' => ['min_viewport' => 768, 'max_container' => 768],
            'default' => ['min_viewport' => 0, 'max_container' => null],
        ],
    ];

    public function testGeneratedCssKeepsTheLeadingCommentInsteadOfDiscardingIt(): void
    {
        $content = $this->generate(self::GRID_CONFIG);

        $this->assertStringStartsWith('/* Progressive Image Container - Custom Breakpoints */', $content);
        $this->assertStringContainsString('@layer vendor {', $content);
    }

    public function testGeneratedCssIncludesMaxWidthFromMaxContainer(): void
    {
        $content = $this->generate(self::GRID_CONFIG);

        $this->assertStringContainsString('max-width: 640px;', $content);
        $this->assertStringContainsString('max-width: 768px;', $content);
    }

    public function testGeneratedCssOmitsMaxWidthWhenMaxContainerIsNull(): void
    {
        $content = $this->generate([
            'columns' => 12,
            'layouts' => [
                'default' => ['min_viewport' => 0, 'max_container' => null],
            ],
        ]);

        $this->assertStringNotContainsString('max-width: ;', $content);
        $this->assertStringNotContainsString('max-width: px;', $content);
    }

    public function testFilesystemIsInjectedInsteadOfInstantiatedInline(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('exists')->willReturn(false);
        $filesystem->expects($this->once())->method('mkdir');
        $filesystem->expects($this->once())
            ->method('dumpFile')
            ->with(
                $this->stringContains('progressive-image-custom.css'),
                $this->stringContains('@layer vendor {')
            );

        $command = new GenerateCustomCssCommand(self::GRID_CONFIG, '/tmp', $filesystem);
        $tester = new CommandTester($command);
        $tester->execute(['path' => 'somewhere']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testRejectsPathArgumentContainingParentDirectoryTraversal(): void
    {
        $command = new GenerateCustomCssCommand(self::GRID_CONFIG, '/tmp', new Filesystem());
        $tester = new CommandTester($command);
        $tester->execute(['path' => '../../etc']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    private function generate(array $gridConfig): string
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('exists')->willReturn(true);
        $captured = null;
        $filesystem->expects($this->once())
            ->method('dumpFile')
            ->willReturnCallback(function (string $path, string $content) use (&$captured): void {
                $captured = $content;
            });

        $command = new GenerateCustomCssCommand($gridConfig, '/tmp', $filesystem);
        $tester = new CommandTester($command);
        $tester->execute(['path' => 'assets/styles']);

        return $captured;
    }
}
