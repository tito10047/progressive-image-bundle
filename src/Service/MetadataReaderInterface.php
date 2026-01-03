<?php

namespace Tito10047\ProgressiveImageBundle\Service;

use Tito10047\ProgressiveImageBundle\DTO\ImageMetadata;

interface MetadataReaderInterface
{
    public function getMetadata(string $src): ?ImageMetadata;
}
