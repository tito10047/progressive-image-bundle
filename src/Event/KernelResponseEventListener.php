<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Tito10047\ProgressiveImageBundle\Event;

use Tito10047\ProgressiveImageBundle\Service\PreloadCollector;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

final class KernelResponseEventListener {

	public function __construct(
		private readonly PreloadCollector $preloadCollector,
	) { }

	public function __invoke(ResponseEvent $event) {
		$response = $event->getResponse();

		$preloads = $this->preloadCollector->getUrls();
		if (empty($preloads)) return;

		$links = [];
		foreach ($preloads as $url => $attr) {
			$link = sprintf('<%s>; rel=preload; as=%s; fetchpriority=%s',
				$url, $attr['as'], $attr['priority']);
			if (!empty($attr['imagesrcset'])) {
				$link .= sprintf('; imagesrcset="%s"', $attr['imagesrcset']);
			}
			if (!empty($attr['imagesizes'])) {
				$link .= sprintf('; imagesizes="%s"', $attr['imagesizes']);
			}
			$links[] = $link;
		}
		$response->headers->set('Link', implode(', ', $links), false);

		$content = $response->getContent();

		$html = "";
		foreach ($preloads as $url => $attr) {
			$html .= sprintf('<link rel="preload" href="%s" as="%s" fetchpriority="%s"',
				$url, $attr['as'], $attr['priority']);
			if (!empty($attr['imagesrcset'])) {
				$html .= sprintf(' imagesrcset="%s"', $attr['imagesrcset']);
			}
			if (!empty($attr['imagesizes'])) {
				$html .= sprintf(' imagesizes="%s"', $attr['imagesizes']);
			}
			$html .= '>';
		}

		$newContent = str_replace('</head>', $html . '</head>', $content);
		$response->setContent($newContent);
	}

}