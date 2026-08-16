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

namespace Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Source;

use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourceImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\SourceReader;

/**
 * Routes a SourcePath to the remote reader when it's an absolute http(s) URL, otherwise to
 * the local (filesystem/asset_mapper/chain resolver) reader. Only registered when
 * variant_source.http.enabled is true — everyone else keeps using ResolverChainSourceReader
 * directly, unchanged.
 */
final readonly class ChainSourceReader implements SourceReader
{
    public function __construct(
        private SourceReader $local,
        private SourceReader $remote,
    ) {
    }

    public function read(SourcePath $path): SourceImage
    {
        return $this->isRemote($path) ? $this->remote->read($path) : $this->local->read($path);
    }

    private function isRemote(SourcePath $path): bool
    {
        return 1 === preg_match('#^https?://#i', $path->value);
    }
}
