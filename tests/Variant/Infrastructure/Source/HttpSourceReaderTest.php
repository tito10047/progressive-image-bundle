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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Infrastructure\Source;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Exception\SourceNotReadable;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Source\HttpSourceReader;

final class HttpSourceReaderTest extends TestCase
{
    public function testThrowsSourceNotReadableWhenHostIsNotInTheAllowlist(): void
    {
        $client = new MockHttpClient(function (): MockResponse {
            self::fail('The HTTP client must never be called for a disallowed host.');
        });
        $reader = new HttpSourceReader($client, ['trusted.example.com'], 5);

        $this->expectException(SourceNotReadable::class);

        $reader->read(new SourcePath('https://evil.example.com/hero.png'));
    }

    public function testThrowsSourceNotReadableOnServerError(): void
    {
        $client = new MockHttpClient(new MockResponse('', ['http_code' => 500]));
        $reader = new HttpSourceReader($client, ['images.example.com'], 5);

        $this->expectException(SourceNotReadable::class);

        $reader->read(new SourcePath('https://images.example.com/hero.png'));
    }

    public function testThrowsSourceNotReadableWhenResponseIsNotAnImage(): void
    {
        $client = new MockHttpClient(new MockResponse('not an image', ['http_code' => 200]));
        $reader = new HttpSourceReader($client, ['images.example.com'], 5);

        $this->expectException(SourceNotReadable::class);

        $reader->read(new SourcePath('https://images.example.com/hero.png'));
    }

    public function testThrowsSourceNotReadableOnTransportFailure(): void
    {
        $client = new MockHttpClient(function (): MockResponse {
            return new MockResponse('', ['error' => 'Connection timed out']);
        });
        $reader = new HttpSourceReader($client, ['images.example.com'], 5);

        $this->expectException(SourceNotReadable::class);

        $reader->read(new SourcePath('https://images.example.com/hero.png'));
    }
}
