<?php

namespace Tito10047\ProgressiveImageBundle\UrlGenerator;

interface ResponsiveImageUrlGeneratorInterface {

	public function generateUrl(string $path, int $targetW, ?int $targetH, ?string $pointInterest = null):string;
}