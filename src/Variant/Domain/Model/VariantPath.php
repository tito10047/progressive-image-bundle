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

    /**
     * Wraps an already-known stored path verbatim — for adapters reconstructing a
     * VariantPath from a directory listing, where the (id, source, format) triple that
     * originally produced it isn't separately known, only the resulting string. Unlike
     * for(), performs no validation: the caller is expected to have obtained this exact
     * value from storage itself.
     */
    public static function fromRaw(string $value): self
    {
        return new self($value);
    }

    /**
     * True if $rawPath follows this class's own layout ({format}/{shard}/{id}/{source}.{ext})
     * and its {source}.{ext} segment matches $source under some OutputFormat — i.e. $rawPath
     * is a variant of $source, regardless of which id or format produced it. Used to filter
     * a full storage listing down to "everything belonging to this source" (variant:remove).
     */
    public static function belongsToSource(string $rawPath, SourcePath $source): bool
    {
        $segments = explode('/', $rawPath);
        if (\count($segments) < 4) {
            return false;
        }

        $formatSegment = array_shift($segments);
        array_shift($segments); // shard
        array_shift($segments); // id
        $sourceWithExtension = implode('/', $segments);

        $format = OutputFormat::tryFrom($formatSegment);

        return null !== $format && $sourceWithExtension === $source->value.'.'.$format->extension();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
