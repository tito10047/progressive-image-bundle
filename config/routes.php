<?php

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Presentation\Controller\ImageVariantController;

return function (RoutingConfigurator $routes): void {
    // Signed query params, not path segments — see QueryPendingUrlBuilder /
    // ImageVariantController for why: a bare path without the matching signature can never
    // regenerate a variant (no enumeration), so there is no separate {format}/{ab}/{hash}
    // path-parameter route to fall back to.
    $routes->add('pgi_variant_serve', '/media/pgi/wait')
        ->controller([ImageVariantController::class, 'serve'])
        ->methods(['GET']);
};
