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

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\UX\TwigComponent\ComponentAttributes;
use Symfony\UX\TwigComponent\ComponentMetadata;
use Symfony\UX\TwigComponent\Event\PreCreateForRenderEvent;
use Symfony\UX\TwigComponent\Event\PreRenderEvent;
use Symfony\UX\TwigComponent\MountedComponent;
use Tito10047\ProgressiveImageBundle\Event\TransparentImageCacheSubscriber;
use Tito10047\ProgressiveImageBundle\Twig\TransparentCacheExtension;
use Twig\Runtime\EscaperRuntime;

final class TransparentImageCacheSubscriberTest extends TestCase
{
    private function createMounted(array $inputProps): MountedComponent
    {
        return new MountedComponent(
            'pgi:Image',
            new \stdClass(),
            new ComponentAttributes([], new EscaperRuntime()),
            $inputProps,
        );
    }

    public function testSecondRequestForTheSameComponentGetsACacheHitWithTheRealHtml(): void
    {
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $subscriber = new TransparentImageCacheSubscriber($cache, true, null);
        $cacheExtension = new TransparentCacheExtension($cache, null);

        // the raw attributes as written in the twig template, e.g. <twig:pgi:Image src="a.jpg"/>
        $inputProps = ['src' => 'a.jpg'];
        $metadata = new ComponentMetadata(['key' => 'pgi:Image', 'template' => '@ProgressiveImage/image.html.twig']);

        // --- request 1: nothing cached yet ---
        $preCreate1 = new PreCreateForRenderEvent('pgi:Image', $inputProps);
        $subscriber->onPreCreate($preCreate1);
        self::assertNull($preCreate1->getRenderedString(), 'first request must be a cache miss');

        $mounted1 = $this->createMounted($inputProps);
        // post-mount variables include defaulted properties not present in the raw input props
        $variables1 = $inputProps + ['retina' => true, 'priority' => false, 'framework' => null, 'preload' => false];
        $preRender1 = new PreRenderEvent($mounted1, $metadata, $variables1);
        $subscriber->onPreRender($preRender1);

        $wrapped = $preRender1->getVariables();
        // simulate cache_wrapper.html.twig rendering the real component then calling |pgi_cache_save
        $cacheExtension->saveToCache('<img src="a.jpg">', $wrapped['pgi_cache_key'], $wrapped['pgi_cache_tag'], $wrapped['pgi_cache_ttl']);

        // --- request 2: identical component, must be served from cache ---
        $preCreate2 = new PreCreateForRenderEvent('pgi:Image', $inputProps);
        $subscriber->onPreCreate($preCreate2);

        self::assertSame('<img src="a.jpg">', $preCreate2->getRenderedString());
    }

    public function testPreCreateDoesNotPoisonTheCacheWithANullEntryOnAMiss(): void
    {
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $subscriber = new TransparentImageCacheSubscriber($cache, true, null);

        $inputProps = ['src' => 'a.jpg'];
        $key = 'pgi_comp_'.md5(serialize($inputProps));

        $subscriber->onPreCreate(new PreCreateForRenderEvent('pgi:Image', $inputProps));

        self::assertFalse($cache->getItem($key)->isHit(), 'a cache miss must not write anything to the cache');
    }
}
