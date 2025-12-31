<?php

namespace Tito10047\ProgressiveImageBundle\Service;

class PreloadCollector {
	private array $urls = [];

	public function add(string $url, string $as = 'image', string $priority = 'high'): void {
		$this->urls[$url] = ['as' => $as, 'priority' => $priority];
	}

	public function getUrls(): array {
		return $this->urls;
	}
}