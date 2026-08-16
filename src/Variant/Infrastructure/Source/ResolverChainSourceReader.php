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

use Tito10047\ProgressiveImageBundle\Exception\PathResolutionException;
use Tito10047\ProgressiveImageBundle\Loader\LoaderInterface;
use Tito10047\ProgressiveImageBundle\Resolver\PathResolverInterface;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Exception\SourceNotReadable;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Dimensions;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourceImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\SourceReader;

/**
 * Wraps the pre-existing Rendering-context resolver/loader (ChainResolver,
 * FileSystemResolver, AssetMapperResolver, FileSystemLoader) — nothing about locating a
 * Source on disk needed reinventing, only the Domain-facing port around it.
 */
final readonly class ResolverChainSourceReader implements SourceReader
{
    public function __construct(
        private PathResolverInterface $resolver,
        private LoaderInterface $loader,
    ) {
    }

    public function read(SourcePath $path): SourceImage
    {
        try {
            $absolutePath = $this->resolver->resolve($path->value);
        } catch (PathResolutionException $e) {
            throw new SourceNotReadable(sprintf('Source "%s" could not be resolved: %s', $path->value, $e->getMessage()), previous: $e);
        }

        $info = @getimagesize($absolutePath);
        if (false === $info) {
            throw new SourceNotReadable(sprintf('Source "%s" is not a readable image.', $path->value));
        }

        $stream = $this->loader->load($absolutePath);

        return new SourceImage($stream, new Dimensions($info[0], $info[1]), $info['mime']);
    }
}
