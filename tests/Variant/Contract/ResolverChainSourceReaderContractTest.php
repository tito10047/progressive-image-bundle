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

use Tito10047\ProgressiveImageBundle\Loader\FileSystemLoader;
use Tito10047\ProgressiveImageBundle\Resolver\FileSystemResolver;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Dimensions;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\SourceReader;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Source\ResolverChainSourceReader;

final class ResolverChainSourceReaderContractTest extends SourceReaderContractTestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/pgi-source-reader-contract-'.bin2hex(random_bytes(8));
        mkdir($this->root);

        $image = imagecreatetruecolor(120, 80);
        imagepng($image, $this->root.'/hero.png');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->root);
    }

    protected function createReader(): SourceReader
    {
        return new ResolverChainSourceReader(new FileSystemResolver([$this->root]), new FileSystemLoader());
    }

    protected function existingSourcePath(): SourcePath
    {
        return new SourcePath('hero.png');
    }

    protected function expectedDimensions(): Dimensions
    {
        return new Dimensions(120, 80);
    }

    protected function expectedMime(): string
    {
        return 'image/png';
    }

    protected function missingSourcePath(): SourcePath
    {
        return new SourcePath('missing.png');
    }
}
