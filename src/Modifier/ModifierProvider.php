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
     * @var array<ModifierInterface>
     */
    private readonly array $modifiers;

    /**
     * @param iterable<ModifierInterface> $modifiers
     */
    public function __construct(
        iterable $modifiers,
    ) {
        // Materialized once: a TaggedIteratorArgument-injected iterable may be a one-shot
        // \Generator, and applyModifiers() re-iterates it once per $modifierString.
        $this->modifiers = is_array($modifiers) ? $modifiers : iterator_to_array($modifiers, false);
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
