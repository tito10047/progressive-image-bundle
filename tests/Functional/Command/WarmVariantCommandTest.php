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

namespace Tito10047\ProgressiveImageBundle\Tests\Functional\Command;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Tito10047\ProgressiveImageBundle\Tests\Integration\PGITestCase;

final class WarmVariantCommandTest extends PGITestCase
{
    private string $storageRoot;

    protected function tearDown(): void
    {
        if (isset($this->storageRoot) && is_dir($this->storageRoot)) {
            $this->removeDirectory($this->storageRoot);
        }
        parent::tearDown();
    }

    public function testWarmsARealVariantOntoDisk(): void
    {
        $this->storageRoot = sys_get_temp_dir().'/pgi-warm-cmd-'.bin2hex(random_bytes(8));
        mkdir($this->storageRoot);
        $storageRoot = $this->storageRoot;

        self::bootKernel([
            'progressive_image' => [
                'resolvers' => [
                    'default' => ['type' => 'filesystem', 'roots' => [__DIR__.'/../Fixtures/images']],
                ],
                'variant_store' => [
                    'storage' => 'test.variant_storage',
                ],
                'filter_sets' => [
                    'thumb_small' => [
                        'filters' => ['thumbnail' => ['size' => [40, 40], 'mode' => 'outbound']],
                    ],
                ],
            ],
        ], function (ContainerBuilder $container) use ($storageRoot): void {
            $container->register('test.variant_storage.adapter', LocalFilesystemAdapter::class)
                ->setArgument('$location', $storageRoot);
            $container->register('test.variant_storage', Filesystem::class)
                ->setArgument('$adapter', new Reference('test.variant_storage.adapter'))
                ->setPublic(true);
        });

        $application = new Application(self::$kernel);
        $command = $application->find('progressive-image:variant:warm');
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['source' => 'test.png', '--filter-set' => ['thumb_small']]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $files = glob($this->storageRoot.'/*/*/*/*.jpg') ?: [];
        self::assertNotEmpty($files, 'the command must have written a real variant file to storage');
    }

    private function removeDirectory(string $dir): void
    {
        $entries = scandir($dir);
        if (false === $entries) {
            return;
        }

        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = $dir.'/'.$entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
