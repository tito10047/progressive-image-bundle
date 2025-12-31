<?php

namespace Tito10047\ProgressiveImageBundle\SrcsetGenerator;

use Tito10047\ProgressiveImageBundle\Decorators\LiipImagineDecorator;

class LiipImagineSrcsetGenerator implements SrcsetGeneratorInterface{
	public function __construct(
		private readonly LiipImagineDecorator $decorator
	) {}
	public function generate(string $path, array $breakpoints, array $context = []): array {
		$baseFilter = $context['filter'] ?? 'progressive_image_filter';
		$set = [];
		foreach ($breakpoints as $breakpoint=>$width) {
			$filter = $baseFilter . '_' . $breakpoint;
			$url = $this->decorator->decorate($path, ['filter' => $filter]);
			$set[$breakpoint] = $url;
		}
		$set['original']= $this->decorator->decorate($path, ['filter' => $baseFilter]);

		return $set;

	}
}