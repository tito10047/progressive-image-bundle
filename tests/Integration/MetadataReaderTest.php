<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Integration;

use Tito10047\ProgressiveImageBundle\Service\MetadataReader;
use Tito10047\ProgressiveImageBundle\Tests\Integration\Kernel\AssetMapperKernelTestCase;

class MetadataReaderTest extends AssetMapperKernelTestCase
{
    public function testMetadataReaderInitialization(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $metadataReader = $container->get(MetadataReader::class);

        $this->assertInstanceOf(MetadataReader::class, $metadataReader);
    }

}
