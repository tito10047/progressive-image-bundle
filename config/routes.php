<?php

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Tito10047\ProgressiveImageBundle\Controller\LiipImagineController;

return function (RoutingConfigurator $routes): void {
	$routes->add('progressive_image_filter', '/progressive-image/filter')
		->controller([LiipImagineController::class, 'index'])
		->methods(['GET']);
};
