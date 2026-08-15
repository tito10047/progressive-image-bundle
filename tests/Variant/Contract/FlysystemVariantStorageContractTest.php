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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Contract;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\VariantStorage;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Flysystem\FlysystemVariantStorage;

final class FlysystemVariantStorageContractTest extends VariantStorageContractTest
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/pgi-variant-storage-'.bin2hex(random_bytes(8));
        mkdir($this->root, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    protected function createStorage(): VariantStorage
    {
        return new FlysystemVariantStorage(new Filesystem(new LocalFilesystemAdapter($this->root)));
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

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
