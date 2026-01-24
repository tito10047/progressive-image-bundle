<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Functional\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Tito10047\ProgressiveImageBundle\Tests\Integration\PGITestCase;

class GenerateCustomCssXsTest extends PGITestCase
{
    private string $tempDir;
    private Filesystem $fs;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->tempDir = sys_get_temp_dir().'/pgi_issue_test_'.uniqid();
        $this->fs->mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->tempDir);
        parent::tearDown();
    }

    public function testGenerateCustomCssWithXsBreakpoint(): void
    {
        self::bootKernel([
            'progressive_image' => [
                'responsive_strategy' => [
                    'grid' => [
                        'framework' => 'custom',
                        'layouts' => [
                            'xs' => ['min_viewport' => 0, 'max_container' => 390],
                        ],
                    ],
                ],
            ],
        ]);

        $application = new Application(self::$kernel);
        $command = $application->find('progressive-image:generate-custom-css');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'path' => $this->tempDir,
        ]);

        $commandTester->assertCommandIsSuccessful();
        $expectedFile = $this->tempDir.'/progressive-image-custom.css';
        $content = file_get_contents($expectedFile);

        // Based on the issue, we expect 'xs' related CSS to be present even if min_viewport is 0
        // Currently, it seems --img-width-xs might be used instead of --img-width
        $this->assertStringContainsString('--img-width-xs', $content, 'CSS should contain --img-width-xs');
    }
}
