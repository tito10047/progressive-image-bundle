<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Resolver;

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Tito10047\ProgressiveImageBundle\Exception\PathResolutionException;

final class AssetMapperResolver implements PathResolverInterface
{
    public function __construct(
        private readonly AssetMapperInterface $assetMapper,
    ) {
    }

    public function resolve(string $path): string
    {
        $path = '/'.mb_ltrim($path, '/');
        foreach ($this->assetMapper->allAssets() as $assetCandidate) {
            if ($path === $assetCandidate->publicPath) {
                return $assetCandidate->sourcePath;
            }
        }
        throw new PathResolutionException(\sprintf('Asset with public path "%s" not found.', $path));
    }
}
