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

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\Modifier;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Modifier\ModifierInterface;
use Tito10047\ProgressiveImageBundle\Modifier\ModifierProvider;

final class ModifierProviderTest extends TestCase
{
    public function testModifiersIterableIsOnlyConsumedOnceEvenWhenAppliedToMultipleModifierStrings(): void
    {
        $upper = new class implements ModifierInterface {
            public function supports(string $modifier): bool
            {
                return 'upper' === $modifier;
            }

            public function modify(string $modifier, array $context): array
            {
                $context['upper'] = true;

                return $context;
            }
        };
        $lower = new class implements ModifierInterface {
            public function supports(string $modifier): bool
            {
                return 'lower' === $modifier;
            }

            public function modify(string $modifier, array $context): array
            {
                $context['lower'] = true;

                return $context;
            }
        };

        // A one-shot generator, as an application-provided iterable (e.g. via
        // TaggedIteratorArgument) might be.
        $modifiers = (function () use ($upper, $lower) {
            yield $upper;
            yield $lower;
        })();

        $provider = new ModifierProvider($modifiers);

        $result = $provider->applyModifiers(['upper', 'lower'], []);

        self::assertSame(['upper' => true, 'lower' => true], $result);
    }
}
