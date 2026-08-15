<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Process\ExecutableFinder;

/**
 * A post-processor enabled with a missing binary must break `cache:clear`/`cache:warmup`,
 * not surface as a generation failure on the first request that needs it (§7 of the DDD
 * plan: "chýbajúca binárka pri enabled: true = exception pri boote, nie za behu").
 */
final class ValidatePostProcessorBinariesPass implements CompilerPassInterface
{
    private const array PROCESSORS = ['jpegoptim', 'pngquant', 'cwebp', 'avifenc'];

    public function process(ContainerBuilder $container): void
    {
        $finder = new ExecutableFinder();

        foreach (self::PROCESSORS as $name) {
            $enabledParam = 'progressive_image.post_processors.'.$name.'.enabled';
            $binParam = 'progressive_image.post_processors.'.$name.'.bin';

            if (!$container->hasParameter($enabledParam) || true !== $container->getParameter($enabledParam)) {
                continue;
            }

            $bin = $container->getParameter($binParam);
            if (!is_string($bin) || !$this->isResolvable($bin, $finder)) {
                throw new \LogicException(sprintf(
                    'progressive_image.post_processors.%s is enabled but its binary "%s" could not be found. Install it or set post_processors.%s.enabled to false.',
                    $name,
                    is_string($bin) ? $bin : get_debug_type($bin),
                    $name
                ));
            }
        }
    }

    /**
     * ExecutableFinder only reliably resolves bare command names via PATH — an absolute or
     * relative path (contains a directory separator) has to be checked directly instead.
     */
    private function isResolvable(string $bin, ExecutableFinder $finder): bool
    {
        if (str_contains($bin, '/') || str_contains($bin, \DIRECTORY_SEPARATOR)) {
            return is_executable($bin);
        }

        return null !== $finder->find($bin);
    }
}
