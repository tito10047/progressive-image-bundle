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

final class ModifierProvider
{
    /**
     * @param iterable<ModifierInterface> $modifiers
     */
    public function __construct(
        private readonly iterable $modifiers,
    ) {
    }

    /**
     * @param string[]             $modifierStrings
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function applyModifiers(array $modifierStrings, array $context): array
    {
        foreach ($modifierStrings as $modifierString) {
            foreach ($this->modifiers as $modifier) {
                if ($modifier->supports($modifierString)) {
                    $context = $modifier->modify($modifierString, $context);
                }
            }
        }

        return $context;
    }
}
