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
    /**
     * @var array<string, string>|null
     */
    private ?array $publicPathToSourcePath = null;

    public function __construct(
        private readonly ?AssetMapperInterface $assetMapper,
    ) {
    }

    public function resolve(string $path): string
    {
        if (null === $this->assetMapper) {
            throw new \LogicException('An "asset_mapper" resolver is configured, but symfony/asset-mapper is not installed. Run "composer require symfony/asset-mapper", or remove this resolver from your "progressive_image.resolvers" configuration.');
        }

        $path = '/'.mb_ltrim($path, '/');

        if (null === $this->publicPathToSourcePath) {
            $this->publicPathToSourcePath = [];
            foreach ($this->assetMapper->allAssets() as $assetCandidate) {
                $this->publicPathToSourcePath[$assetCandidate->publicPath] = $assetCandidate->sourcePath;
            }
        }

        if (!isset($this->publicPathToSourcePath[$path])) {
            throw new PathResolutionException(\sprintf('Asset with public path "%s" not found.', $path));
        }

        return $this->publicPathToSourcePath[$path];
    }
}
