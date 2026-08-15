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

namespace Tito10047\ProgressiveImageBundle\Variant\Domain\Model;

use Tito10047\ProgressiveImageBundle\Variant\Domain\Exception\InvalidFilterDefinition;

final readonly class SourcePath implements \Stringable
{
    public string $value;

    public function __construct(string $value)
    {
        $normalized = ltrim(trim($value), '/');

        if ('' === $normalized) {
            throw new InvalidFilterDefinition('SourcePath must not be empty.');
        }

        foreach (explode('/', $normalized) as $segment) {
            if ('..' === $segment) {
                throw new InvalidFilterDefinition(sprintf('SourcePath must not contain path traversal segments, got "%s".', $value));
            }
        }

        $this->value = $normalized;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
