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

enum OutputFormat: string
{
    case Jpeg = 'jpeg';
    case Png = 'png';
    case Webp = 'webp';
    case Avif = 'avif';

    public function mime(): string
    {
        return match ($this) {
            self::Jpeg => 'image/jpeg',
            self::Png => 'image/png',
            self::Webp => 'image/webp',
            self::Avif => 'image/avif',
        };
    }

    public function extension(): string
    {
        return match ($this) {
            self::Jpeg => 'jpg',
            self::Png => 'png',
            self::Webp => 'webp',
            self::Avif => 'avif',
        };
    }

    /**
     * Whether encoding this format actually honours a quality setting. PngEncoder (see
     * InterventionImageManipulator::encoderFor()) has no quality parameter at all.
     */
    public function usesQuality(): bool
    {
        return self::Png !== $this;
    }
}
