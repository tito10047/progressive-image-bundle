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

/**
 * The single source of truth for the variant store layout:
 * {format}/{ab}/{hash}/{source-path}.{ext}
 *
 * Used by VariantStorage, the serving controller and the URL generator alike —
 * no other code should assemble a variant path via sprintf().
 */
final readonly class VariantPath implements \Stringable
{
    private function __construct(public string $value)
    {
    }

    public static function for(VariantId $id, SourcePath $source, OutputFormat $format): self
    {
        $shard = substr($id->value, 0, 2);

        return new self(sprintf(
            '%s/%s/%s/%s.%s',
            $format->value,
            $shard,
            $id->value,
            $source->value,
            $format->extension()
        ));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
