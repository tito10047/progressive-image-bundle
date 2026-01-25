<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Functional\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Tito10047\ProgressiveImageBundle\Tests\Integration\PGITestCase;

class GenerateCustomCssCommandTest extends PGITestCase
{
    private string $tempDir;
    private Filesystem $fs;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->tempDir = sys_get_temp_dir().'/pgi_command_test_'.uniqid();
        $this->fs->mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->tempDir);
        parent::tearDown();
    }

    public function testExecute(): void
    {
        self::bootKernel([
            'progressive_image' => [
                'responsive_strategy' => [
                    'grid' => [
                        'framework' => 'custom',
                        'layouts' => [
                            'sm' => ['min_viewport' => 640, 'max_container' => 640],
                            'md' => ['min_viewport' => 768, 'max_container' => 768],
                            'default' => ['min_viewport' => 0, 'max_container' => null],
                        ],
                    ],
                ],
            ],
        ]);

        $application = new Application(self::$kernel);
        $command = $application->find('progressive-image:generate-custom-css');
        $commandTester = new CommandTester($command);

        $outputPath = $this->tempDir;
        $commandTester->execute([
            'path' => $outputPath,
        ]);

        $commandTester->assertCommandIsSuccessful();
        $expectedFile = $outputPath.'/progressive-image-custom.css';
        $this->assertFileExists($expectedFile);

        $content = file_get_contents($expectedFile);

        // Copy to var/ for user check as requested
        $varPath = self::$kernel->getProjectDir().'/var';
        if (!is_dir($varPath)) {
            mkdir($varPath, 0777, true);
        }
        file_put_contents($varPath.'/progressive-image-custom.css', $content);

        $this->assertStringContainsString('.progressive-image-container', $content);
        $this->assertStringContainsString('@media (min-width: 640px)', $content);
        $this->assertStringContainsString('@media (min-width: 768px)', $content);
        $this->assertStringContainsString('--img-width-sm', $content);
        $this->assertStringContainsString('--img-width-md', $content);

        // Check nested variables in root
        $this->assertStringContainsString('width: var(--img-width-md,', $content);
        $this->assertStringContainsString('var(--img-width-sm,', $content);
        $this->assertStringContainsString('var(--img-width-default,', $content);
        $this->assertStringContainsString('var(--img-width)));', $content);

        // Check media query content
        $this->assertStringContainsString('width: var(--img-width-sm, var(--img-width-default, var(--img-width)));', $content);
        $this->assertStringContainsString('width: var(--img-width-md, var(--img-width-sm, var(--img-width-default, var(--img-width))));', $content);
        $this->assertStringContainsString('@media (max-width: 640px)', $content);
    }
}
