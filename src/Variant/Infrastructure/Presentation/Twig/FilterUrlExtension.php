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

namespace Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Presentation\Twig;

use Tito10047\ProgressiveImageBundle\Variant\Application\Handler\ResolveFilterUrlHandler;
use Tito10047\ProgressiveImageBundle\Variant\Application\Query\ResolveFilterUrl;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The standalone URL-generation surface the <twig:pgi:Image> component doesn't provide:
 * a plain string URL for a named filter set, usable anywhere a component render doesn't
 * fit — <img> tags built by hand, og:image meta, JSON/API responses, emails, sitemaps.
 */
final class FilterUrlExtension extends AbstractExtension
{
    public function __construct(
        private readonly ResolveFilterUrlHandler $resolveHandler,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('pgi_filter', $this->resolve(...)),
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    public function resolve(string $path, string $filterSet, array $context = []): string
    {
        $resolved = ($this->resolveHandler)(new ResolveFilterUrl(new SourcePath($path), $filterSet, $context));

        return $resolved->url;
    }
}
