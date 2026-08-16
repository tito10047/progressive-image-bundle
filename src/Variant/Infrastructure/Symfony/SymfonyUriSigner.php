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

namespace Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Symfony;

use Symfony\Component\HttpFoundation\UriSigner;
use Tito10047\ProgressiveImageBundle\Variant\Application\Port\UrlSigner;

final readonly class SymfonyUriSigner implements UrlSigner
{
    public function __construct(private UriSigner $signer)
    {
    }

    public function sign(string $url): string
    {
        return $this->signer->sign($url);
    }

    public function check(string $url): bool
    {
        return $this->signer->check($url);
    }
}
