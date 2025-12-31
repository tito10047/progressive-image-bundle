<?php

namespace Tito10047\ProgressiveImageBundle\Event;

use Tito10047\ProgressiveImageBundle\Service\PreloadCollector;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class KernelResponseEventListener {

	public function __construct(
		private readonly PreloadCollector $preloadCollector,
	) { }

	public function __invoke(ResponseEvent $event) {
		$response = $event->getResponse();

		$preloads = $this->preloadCollector->getUrls();
		if (empty($preloads)) return;

		$links = [];
		foreach ($preloads as $url => $attr) {
			$links[] = sprintf('<%s>; rel=preload; as=%s; fetchpriority=%s',
				$url, $attr['as'], $attr['priority']);
		}
		$response->headers->set('Link', implode(', ', $links), false);

		$content = $response->getContent();

		$html = "";
		foreach ($preloads as $url => $attr) {
			$html .= sprintf('<link rel="preload" href="%s" as="%s" fetchpriority="%s">',
				$url, $attr['as'], $attr['priority']);
		}

		$newContent = str_replace('</head>', $html . '</head>', $content);
		$response->setContent($newContent);
	}

}