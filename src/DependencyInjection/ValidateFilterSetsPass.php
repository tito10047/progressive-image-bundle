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
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\FilterFactory;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\FilterSetRegistry;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Exception\InvalidFilterDefinition;

/**
 * A typo in progressive_image.filter_sets must break `cache:clear`/`cache:warmup`, not
 * surface as a 500 the first time a page renders. FilterSetRegistry already validates
 * eagerly in its constructor (see the class itself) — this pass just triggers that
 * construction at compile time, directly in PHP rather than through the container, so no
 * request is needed to find out the config is broken.
 */
final class ValidateFilterSetsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('progressive_image.variant.filter_sets')) {
            return;
        }

        /** @var array<string, array<string, mixed>> $filterSets */
        $filterSets = $container->getParameter('progressive_image.variant.filter_sets');

        try {
            new FilterSetRegistry($filterSets, new FilterFactory());
        } catch (InvalidFilterDefinition $e) {
            throw new \LogicException(sprintf('Invalid "progressive_image.filter_sets" configuration: %s', $e->getMessage()), previous: $e);
        }
    }
}
