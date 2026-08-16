<?php

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Presentation\Controller\ImageVariantController;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Presentation\Controller\ResolveFilterController;

return function (RoutingConfigurator $routes): void {
    // Signed query params, not path segments — see QueryPendingUrlBuilder /
    // ImageVariantController for why: a bare path without the matching signature can never
    // regenerate a variant (no enumeration), so there is no separate {format}/{ab}/{hash}
    // path-parameter route to fall back to.
    $routes->add('pgi_variant_serve', '/media/pgi/wait')
        ->controller([ImageVariantController::class, 'serve'])
        ->methods(['GET']);

    // Deliberately unsigned — {filterSet} must be a known filter_sets config entry (checked
    // by VariantSpecFactory), never arbitrary caller-chosen filter params, so there is no
    // enumeration/DoS surface beyond "any path, any filter set the app owner configured".
    $routes->add('pgi_variant_resolve', '/media/pgi/resolve/{filterSet}/{path}')
        ->controller([ResolveFilterController::class, 'resolve'])
        ->requirements(['path' => '.+'])
        ->methods(['GET']);
};
