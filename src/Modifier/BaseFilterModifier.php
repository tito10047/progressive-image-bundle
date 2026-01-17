<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Modifier;

class BaseFilterModifier implements ModifierInterface
{
    public function supports(string $modifier): bool
    {
        return true;
    }

    public function modify(string $modifier, array $context): array
    {
        if (!isset($context['filter'])) {
            $context['filter'] = $modifier;
        }

        return $context;
    }
}
