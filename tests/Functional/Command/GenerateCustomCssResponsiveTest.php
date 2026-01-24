<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Functional\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Tito10047\ProgressiveImageBundle\Tests\Integration\PGITestCase;

class GenerateCustomCssResponsiveTest extends PGITestCase
{
    private string $tempDir;
    private Filesystem $fs;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->tempDir = sys_get_temp_dir().'/pgi_tdd_test_'.uniqid();
        $this->fs->mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->tempDir);
        parent::tearDown();
    }

    public function testExecuteWithMaxWidthForSmallestBreakpoint(): void
    {
        self::bootKernel([
            'progressive_image' => [
                'responsive_strategy' => [
                    'grid' => [
                        'framework' => 'custom',
                        'layouts' => [
                            'xs' => ['min_viewport' => 0, 'max_container' => 390],
                            'sm' => ['min_viewport' => 640, 'max_container' => 600],
                        ],
                    ],
                ],
            ],
        ]);

        $application = new Application(self::$kernel);
        $command = $application->find('progressive-image:generate-custom-css');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['path' => $this->tempDir]);

        $commandTester->assertCommandIsSuccessful();
        $content = file_get_contents($this->tempDir.'/progressive-image-custom.css');

        // Check xs breakpoint with max-width
        $expectedXs = "/* xs: 0px */\n@media (max-width: 640px) {\n\t\t.progressive-image-container {\n\t\t\twidth: var(--img-width-xs);\n\t\t\taspect-ratio: var(--img-aspect-xs);\n\t\t}\n\t}";
        $this->assertStringContainsString($expectedXs, $content);

        // Check sm breakpoint with fallback to xs
        $expectedSm = "/* sm: 640px */\n@media (min-width: 640px) {\n\t\t.progressive-image-container {\n\t\t\twidth: var(--img-width-sm, var(--img-width-xs));\n\t\t\taspect-ratio: var(--img-aspect-sm, var(--img-aspect-xs));\n\t\t}\n\t}";
        $this->assertStringContainsString($expectedSm, $content);
    }
}
