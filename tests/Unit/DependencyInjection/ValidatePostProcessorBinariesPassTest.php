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

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tito10047\ProgressiveImageBundle\DependencyInjection\ValidatePostProcessorBinariesPass;

final class ValidatePostProcessorBinariesPassTest extends TestCase
{
    private function makeContainer(bool $enabled, string $bin): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('progressive_image.post_processors.jpegoptim.enabled', $enabled);
        $container->setParameter('progressive_image.post_processors.jpegoptim.bin', $bin);

        return $container;
    }

    public function testDoesNothingWhenDisabled(): void
    {
        $container = $this->makeContainer(false, '/does/not/exist');

        (new ValidatePostProcessorBinariesPass())->process($container);

        $this->addToAssertionCount(1); // no exception thrown
    }

    public function testDoesNothingWhenParametersAreAbsent(): void
    {
        (new ValidatePostProcessorBinariesPass())->process(new ContainerBuilder());

        $this->addToAssertionCount(1);
    }

    public function testThrowsWhenEnabledWithAMissingAbsolutePath(): void
    {
        $container = $this->makeContainer(true, '/definitely/does/not/exist/jpegoptim');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/jpegoptim/');

        (new ValidatePostProcessorBinariesPass())->process($container);
    }

    public function testThrowsWhenEnabledWithAnUnresolvableBareName(): void
    {
        $container = $this->makeContainer(true, 'this-binary-definitely-does-not-exist-anywhere');

        $this->expectException(\LogicException::class);

        (new ValidatePostProcessorBinariesPass())->process($container);
    }

    public function testPassesWhenEnabledWithAResolvableAbsolutePath(): void
    {
        $container = $this->makeContainer(true, '/bin/sh');

        (new ValidatePostProcessorBinariesPass())->process($container);

        $this->addToAssertionCount(1);
    }

    public function testPassesWhenEnabledWithABareNameResolvableViaPath(): void
    {
        $container = $this->makeContainer(true, 'php');

        (new ValidatePostProcessorBinariesPass())->process($container);

        $this->addToAssertionCount(1);
    }
}
