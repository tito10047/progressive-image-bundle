<?php

namespace Tito10047\ProgressiveImageBundle\Event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\UX\TwigComponent\Event\PreCreateForRenderEvent;
use Symfony\UX\TwigComponent\Event\PreRenderEvent;
use Symfony\Contracts\Cache\CacheInterface;

class TransparentImageCacheSubscriber implements EventSubscriberInterface
{
	public function __construct(
		private readonly CacheInterface $cache,
		private readonly bool           $enabled,
		private readonly ?int $ttl
	) {}

	public static function getSubscribedEvents(): array
	{
		return [
			PreCreateForRenderEvent::class => ['onPreCreate', 10],
			PreRenderEvent::class => ['onPreRender', 10],
		];
	}

	public function onPreCreate(PreCreateForRenderEvent $event): void
	{
		if (!$this->enabled || $event->getName() !== 'pgi:Image') {
			return;
		}

		$key = $this->generateKey($event->getInputProps());
		$cachedHtml = $this->cache->get($key, fn() => null);

		if ($cachedHtml) {
			$event->setRenderedString($cachedHtml);
		}
	}

	public function onPreRender(PreRenderEvent $event): void
	{
		if (!$this->enabled || $event->getMetadata()->getName() !== 'pgi:Image') {
			return;
		}

		// Ak sa dostaneme sem, znamená to, že v keši nič nebolo (inak by PreCreateForRenderEvent zastavil renderovanie)
		$key = $this->generateKey($event->getVariables());

		// Obalíme pôvodnú šablónu do wrappera, ktorý výsledok uloží do keše
		$variables = $event->getVariables();
		$variables['pgi_original_template'] = $event->getTemplate();
		$variables['pgi_cache_key'] = $key;

		$event->setVariables($variables);
		$event->setTemplate('@ProgressiveImage/cache_wrapper.html.twig');
	}

	private function generateKey(array $vars): string
	{
		// Odstránime interné pgi premenné ak tam sú, aby neovplyvňovali kľúč
		unset($vars['pgi_original_template'], $vars['pgi_cache_key']);

		// Kľúč musí obsahovať všetko, čo mení HTML výstup
		return 'pgi_comp_' . md5(serialize($vars));
	}
}