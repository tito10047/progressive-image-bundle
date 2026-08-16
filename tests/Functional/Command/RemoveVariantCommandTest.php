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

final class RemoveVariantCommandTest extends PGITestCase
{
    private string $storageRoot;

    protected function tearDown(): void
    {
        if (isset($this->storageRoot) && is_dir($this->storageRoot)) {
            $this->removeDirectory($this->storageRoot);
        }
        parent::tearDown();
    }

    public function testRemovesAWarmedVariantFromRealStorage(): void
    {
        $this->storageRoot = sys_get_temp_dir().'/pgi-remove-cmd-'.bin2hex(random_bytes(8));
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

        $warmTester = new CommandTester($application->find('progressive-image:variant:warm'));
        $warmTester->execute(['source' => 'test.png', '--filter-set' => ['thumb_small']]);
        self::assertNotEmpty(glob($this->storageRoot.'/*/*/*/*.jpg'), 'setup: the variant must exist before removing it');

        $removeTester = new CommandTester($application->find('progressive-image:variant:remove'));
        $exitCode = $removeTester->execute(['source' => 'test.png']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertEmpty(glob($this->storageRoot.'/*/*/*/*.jpg') ?: [], 'the variant file must be gone after removal');
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
