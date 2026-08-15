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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Infrastructure\Symfony;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Symfony\DefaultOriginalUrlResolver;

final class DefaultOriginalUrlResolverTest extends TestCase
{
    public function testResolvesToTheSourcePathPrefixedWithASlash(): void
    {
        $resolver = new DefaultOriginalUrlResolver();

        self::assertSame('/uploads/hero.jpg', $resolver->resolve(new SourcePath('uploads/hero.jpg')));
    }
}
