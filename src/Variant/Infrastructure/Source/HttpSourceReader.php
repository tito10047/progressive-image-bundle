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

namespace Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Source;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Exception\SourceNotReadable;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Dimensions;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourceImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\SourceReader;

/**
 * Reads a source image over HTTP(S). Fetching an arbitrary caller-supplied URL is an SSRF
 * surface, so this only ever fetches hosts present in the configured allowlist — everything
 * else fails closed as SourceNotReadable before any request is made.
 */
final readonly class HttpSourceReader implements SourceReader
{
    /**
     * @param string[] $allowedHosts
     */
    public function __construct(
        private HttpClientInterface $client,
        private array $allowedHosts,
        private int $timeoutSeconds,
    ) {
    }

    public function read(SourcePath $path): SourceImage
    {
        $host = parse_url($path->value, PHP_URL_HOST);
        if (!\is_string($host) || !\in_array($host, $this->allowedHosts, true)) {
            throw new SourceNotReadable(sprintf('Host "%s" of source "%s" is not in the configured variant_source.http.allowed_hosts list.', $host ?: '(none)', $path->value));
        }

        try {
            $response = $this->client->request('GET', $path->value, [
                'timeout' => $this->timeoutSeconds,
                'throw' => false,
            ]);

            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                throw new SourceNotReadable(sprintf('Source "%s" responded with HTTP %d.', $path->value, $status));
            }

            $content = $response->getContent();
        } catch (ExceptionInterface $e) {
            throw new SourceNotReadable(sprintf('Source "%s" could not be fetched: %s', $path->value, $e->getMessage()), previous: $e);
        }

        $info = @getimagesizefromstring($content);
        if (false === $info) {
            throw new SourceNotReadable(sprintf('Source "%s" is not a readable image.', $path->value));
        }

        $stream = fopen('php://temp', 'r+b');
        fwrite($stream, $content);
        rewind($stream);

        return new SourceImage($stream, new Dimensions($info[0], $info[1]), $info['mime']);
    }
}
