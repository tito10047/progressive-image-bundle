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

namespace Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Presentation\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tito10047\ProgressiveImageBundle\Variant\Application\Command\GenerateVariant;
use Tito10047\ProgressiveImageBundle\Variant\Application\Handler\GenerateVariantHandler;
use Tito10047\ProgressiveImageBundle\Variant\Application\Port\OriginalUrlResolver;
use Tito10047\ProgressiveImageBundle\Variant\Application\Port\UrlSigner;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\VariantSpecFactory;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Dimensions;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\PointOfInterest;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Variant;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\VariantStorage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Service\VariantIdHasher;

/**
 * Route target for two cases (§8.1 of the DDD plan): the "wait" fallback's signed URL
 * (query params built by QueryPendingUrlBuilder), and an nginx try_files miss on an
 * already-existing variant path that got evicted/purged after the HTML referencing it was
 * sent. Both need a valid signature — without it there is no way to request generation of
 * an arbitrary path, which is the anti-enumeration property the plan calls for.
 *
 * Always redirects, never streams (§8.1/F6): the next hit goes straight to the storage's
 * public URL without touching PHP again, identically for local nginx and S3+CDN.
 */
final readonly class ImageVariantController
{
    public function __construct(
        private VariantSpecFactory $specFactory,
        private VariantIdHasher $hasher,
        private VariantStorage $storage,
        private GenerateVariantHandler $generateHandler,
        private OriginalUrlResolver $originalUrlResolver,
        private UrlSigner $urlSigner,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function serve(
        Request $request,
        #[MapQueryParameter] string $source,
        #[MapQueryParameter] int $width,
        #[MapQueryParameter] int $height,
        #[MapQueryParameter] ?string $filterSet = null,
        #[MapQueryParameter] ?int $poiX = null,
        #[MapQueryParameter] ?int $poiY = null,
        #[MapQueryParameter] ?int $origW = null,
        #[MapQueryParameter] ?int $origH = null,
        #[MapQueryParameter] ?string $context = null,
    ): Response {
        if (!$this->urlSigner->check($request->getUri())) {
            throw new NotFoundHttpException('Invalid or missing signature.');
        }

        $sourcePath = new SourcePath($source);
        $poi = (null !== $poiX && null !== $poiY) ? new PointOfInterest($poiX, $poiY) : null;
        $originalDimensions = (null !== $origW && null !== $origH) ? new Dimensions($origW, $origH) : null;
        $contextArray = [];
        if (null !== $context) {
            $decoded = json_decode($context, true, flags: \JSON_THROW_ON_ERROR);
            if (!is_array($decoded) || ([] !== $decoded && array_is_list($decoded))) {
                throw new NotFoundHttpException('Invalid context payload.');
            }

            foreach ($decoded as $key => $value) {
                if (!is_string($key)) {
                    throw new NotFoundHttpException('Invalid context payload.');
                }
                $contextArray[$key] = $value;
            }
        }

        $spec = $this->specFactory->create($width, $height, $filterSet, $poi, $originalDimensions, $contextArray);
        $variant = Variant::request($sourcePath, $spec, $this->hasher);
        $path = $variant->path();

        if (!$this->storage->exists($path)) {
            try {
                ($this->generateHandler)(new GenerateVariant($sourcePath, $spec));
            } catch (\Throwable $e) {
                // GenerateVariantHandler already recorded the fail marker and published
                // VariantGenerationFailed — this falls through to the original-image
                // fallback below, but the failure itself must still be visible somewhere,
                // even in an app that never registered a PSR-3 logger service (the DI
                // wiring uses IGNORE_ON_INVALID_REFERENCE, so $this->logger can be null).
                if ($this->logger) {
                    $this->logger->warning('Synchronous variant generation failed; falling back to the original image.', [
                        'source' => $sourcePath->value,
                        'exception' => $e,
                    ]);
                } else {
                    error_log(sprintf('Synchronous variant generation failed for source "%s": %s', $sourcePath->value, $e->getMessage()));
                }
            }
        }

        if ($this->storage->exists($path)) {
            return new RedirectResponse($this->storage->publicPath($path), 302, ['Cache-Control' => 'no-store, must-revalidate']);
        }

        return new RedirectResponse($this->originalUrlResolver->resolve($sourcePath), 302, ['Cache-Control' => 'no-store, must-revalidate']);
    }
}
