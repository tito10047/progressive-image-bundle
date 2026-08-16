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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Infrastructure\Presentation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\PendingGenerationTracker;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantId;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Presentation\EventListener\ResponseCacheOverrideListener;

final class ResponseCacheOverrideListenerTest extends TestCase
{
    private function makeEvent(Response $response): ResponseEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);

        return new ResponseEvent($kernel, Request::create('/'), Kernel::MAIN_REQUEST, $response);
    }

    public function testLeavesTheResponseUntouchedWhenNothingIsPending(): void
    {
        $tracker = new PendingGenerationTracker();
        $listener = new ResponseCacheOverrideListener($tracker);

        $response = new Response('ok');
        $response->setPublic();
        $response->setMaxAge(3600);
        $response->setEtag('abc');

        $event = $this->makeEvent($response);
        $listener($event);

        self::assertTrue($event->getResponse()->headers->hasCacheControlDirective('public'));
        self::assertSame('"abc"', $event->getResponse()->getEtag());
    }

    public function testOverridesCacheHeadersWhenSomethingIsPending(): void
    {
        $tracker = new PendingGenerationTracker();
        $tracker->markPending(new VariantId('abc'));
        $listener = new ResponseCacheOverrideListener($tracker);

        $response = new Response('ok');
        $response->setPublic();
        $response->setMaxAge(3600);
        $response->setEtag('abc');
        $response->setLastModified(new \DateTimeImmutable());
        $response->headers->set('Expires', 'Wed, 21 Oct 2099 07:28:00 GMT');

        $event = $this->makeEvent($response);
        $listener($event);

        $headers = $event->getResponse()->headers;
        self::assertTrue($headers->hasCacheControlDirective('no-store'));
        self::assertTrue($headers->hasCacheControlDirective('no-cache'));
        self::assertTrue($headers->hasCacheControlDirective('must-revalidate'));
        self::assertTrue($headers->hasCacheControlDirective('private'));
        self::assertSame('0', $headers->getCacheControlDirective('max-age'));
        self::assertNull($event->getResponse()->getEtag());
        self::assertNull($event->getResponse()->getLastModified());
        self::assertFalse($headers->has('Expires'));
        self::assertSame('no-store', $headers->get('Surrogate-Control'));
    }
}
