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

namespace Tito10047\ProgressiveImageBundle\Variant\Domain\Service;

use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantId;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantSpec;

/**
 * Computes the deterministic, content-addressed VariantId from (source, spec).
 *
 * The canonical JSON payload — including the 'v' hash-schema version key — is a stable
 * contract: changing its shape changes every VariantId, which invalidates the entire
 * variant store. Bump 'v' deliberately if this ever needs to change; never silently.
 */
final readonly class VariantIdHasher
{
    private const int HASH_SCHEMA_VERSION = 1;

    public function __construct(private string $secret)
    {
    }

    public function hash(SourcePath $source, VariantSpec $spec): VariantId
    {
        $canonical = json_encode(
            ['src' => $source->value, 'spec' => $spec->canonical(), 'v' => self::HASH_SCHEMA_VERSION],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        );

        $binary = hash_hmac('sha256', $canonical, $this->secret, true);

        return new VariantId(self::base64url($binary));
    }

    private static function base64url(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}
