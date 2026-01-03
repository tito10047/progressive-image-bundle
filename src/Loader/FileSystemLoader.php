<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Loader;

use Tito10047\ProgressiveImageBundle\Exception\PathResolutionException;

final class FileSystemLoader implements LoaderInterface
{
    /**
     * @var resource|false|null
     */
    private $file;

    public function load(string $path)
    {
        if (!file_exists($path) || !is_file($path)) {
            throw new PathResolutionException("Path $path does not exist or is not a file.");
        }

        return $this->file = fopen($path, 'r');
    }

    public function __destruct()
    {
        if (!$this->file || !is_resource($this->file)) {
            $this->file = null;

            return;
        }
        fclose($this->file);
        $this->file = null;
    }
}
