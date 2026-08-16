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

use Tito10047\ProgressiveImageBundle\Variant\Domain\Event\VariantGenerated;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Event\VariantGenerationFailed;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Exception\VariantDomainException;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Service\VariantIdHasher;

/**
 * Identity is content-addressed (VariantId), not autoincrement: two workers handling the
 * same command produce the same id, which gives idempotent generation for free.
 */
final class Variant
{
    private function __construct(
        public readonly VariantId $id,
        public readonly SourcePath $source,
        public readonly VariantSpec $spec,
        private VariantState $state,
    ) {
    }

    public static function request(SourcePath $source, VariantSpec $spec, VariantIdHasher $hasher): self
    {
        return new self($hasher->hash($source, $spec), $source, $spec, VariantState::Requested);
    }

    public function state(): VariantState
    {
        return $this->state;
    }

    public function path(): VariantPath
    {
        return VariantPath::for($this->id, $this->source, $this->spec->format);
    }

    public function startGenerating(): void
    {
        if (VariantState::Ready === $this->state) {
            throw new VariantDomainException('Variant already generated.');
        }

        $this->state = VariantState::Generating;
    }

    public function markReady(): VariantGenerated
    {
        if (VariantState::Generating !== $this->state) {
            throw new VariantDomainException('Variant can only be marked ready while generating.');
        }

        $this->state = VariantState::Ready;

        return new VariantGenerated($this->id);
    }

    public function markFailed(\Throwable $cause): VariantGenerationFailed
    {
        if (VariantState::Generating !== $this->state) {
            throw new VariantDomainException('Variant can only be marked failed while generating.');
        }

        $this->state = VariantState::Failed;

        return new VariantGenerationFailed($this->id, $cause);
    }
}
