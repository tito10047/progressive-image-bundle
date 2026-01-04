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

namespace Tito10047\ProgressiveImageBundle\Controller;

use Liip\ImagineBundle\Config\Controller\ControllerConfig;
use Liip\ImagineBundle\Exception\Binary\Loader\NotLoadableException;
use Liip\ImagineBundle\Exception\Imagine\Filter\NonExistingFilterException;
use Liip\ImagineBundle\Imagine\Cache\Helper\PathHelper;
use Liip\ImagineBundle\Imagine\Data\DataManager;
use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;
use Liip\ImagineBundle\Service\FilterService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tito10047\ProgressiveImageBundle\Service\LiipImagineRuntimeConfigGeneratorInterface;
use Tito10047\ProgressiveImageBundle\Service\MetadataReaderInterface;

final class LiipImagineController
{
    public function __construct(
        private readonly UriSigner $signer,
        private readonly FilterService $filterService,
        private readonly DataManager $dataManager,
        private readonly FilterConfiguration $filterConfiguration,
        private readonly ControllerConfig $controllerConfig,
        private readonly LiipImagineRuntimeConfigGeneratorInterface $runtimeConfigGenerator,
        private readonly MetadataReaderInterface $metadataReader,
    ) {
    }

    public function index(
        Request $request,
        #[MapQueryParameter] string $path,
        #[MapQueryParameter] int $width,
        #[MapQueryParameter] int $height,
        #[MapQueryParameter] ?string $filter = null,
        #[MapQueryParameter] ?string $pointInterest = null,
    ): Response {
        $path = PathHelper::urlPathToFilePath($path);

        $origWidth = null;
        $origHeight = null;
        if ($pointInterest) {
            $metadata = $this->metadataReader->getMetadata($path);
            $origWidth = $metadata->width;
            $origHeight = $metadata->height;
        }

        $result = $this->runtimeConfigGenerator->generate($width, $height, $filter, $pointInterest, $origWidth, $origHeight);
        $filterName = $result['filterName'];
        $config = $result['config'];

        $this->filterConfiguration->set($filterName, $config);

        if (true !== $this->signer->checkRequest($request)) {
            throw new BadRequestHttpException(\sprintf('Signed url does not pass the sign check for path "%s" and filter "%s"', $path, $filter));
        }

        return $this->createRedirectResponse(function () use ($path, $filterName) {
			$url = $this->filterService->getUrlOfFilteredImage($path, $filterName);

			return str_replace('/resolve/', '/', $url);
        }, $path, $filterName);
    }

    private function createRedirectResponse(\Closure $url, string $path, string $filter): RedirectResponse
    {
        try {
            return new RedirectResponse($url(), $this->controllerConfig->getRedirectResponseCode());
        } catch (NotLoadableException $exception) {
            if (null !== $this->dataManager->getDefaultImageUrl($filter)) {
                return new RedirectResponse($this->dataManager->getDefaultImageUrl($filter));
            }

            throw new NotFoundHttpException(\sprintf('Source image for path "%s" could not be found', $path), $exception);
        } catch (NonExistingFilterException $exception) {
            throw new NotFoundHttpException(\sprintf('Requested non-existing filter "%s"', $filter), $exception);
        } catch (\RuntimeException $exception) {
            throw new \RuntimeException(\sprintf('Unable to create image for path "%s" and filter "%s". Message was "%s"', $path, $filter, $exception->getMessage()), 0, $exception);
        }
    }
}
