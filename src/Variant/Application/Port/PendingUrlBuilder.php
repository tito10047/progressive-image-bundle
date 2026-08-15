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

namespace Tito10047\ProgressiveImageBundle\Variant\Application\Port;

use Tito10047\ProgressiveImageBundle\Variant\Application\Query\ResolveVariantUrl;

/**
 * Builds the unsigned "wait" URL (route 'pgi_variant_serve' with the spec as a query
 * string, per §6.1c of the DDD plan) — Infrastructure owns the actual route/query
 * encoding via Symfony's UrlGeneratorInterface. ResolveVariantUrlHandler signs whatever
 * this returns via UrlSigner before handing it to the caller.
 *
 * Takes the original query (not the resolved VariantSpec) deliberately: the serving
 * controller rebuilds the spec via the same VariantSpecFactory::create() call, guaranteeing
 * an identical VariantId, rather than deserializing arbitrary objects out of a request.
 */
interface PendingUrlBuilder
{
    public function build(ResolveVariantUrl $query): string;
}
