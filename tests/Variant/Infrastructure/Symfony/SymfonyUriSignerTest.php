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
use Symfony\Component\HttpFoundation\UriSigner;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Symfony\SymfonyUriSigner;

final class SymfonyUriSignerTest extends TestCase
{
    private SymfonyUriSigner $signer;

    protected function setUp(): void
    {
        $this->signer = new SymfonyUriSigner(new UriSigner('secret'));
    }

    public function testSignAddsASignatureQueryParameter(): void
    {
        $signed = $this->signer->sign('https://example.com/media/pgi/wait/uploads/hero.jpg');

        self::assertStringContainsString('_hash=', $signed);
    }

    public function testCheckAcceptsAUrlSignedByTheSameSigner(): void
    {
        $signed = $this->signer->sign('https://example.com/media/pgi/wait/uploads/hero.jpg');

        self::assertTrue($this->signer->check($signed));
    }

    public function testCheckRejectsATamperedUrl(): void
    {
        $signed = $this->signer->sign('https://example.com/media/pgi/wait/uploads/hero.jpg');
        $tampered = str_replace('hero.jpg', 'evil.jpg', $signed);

        self::assertFalse($this->signer->check($tampered));
    }

    public function testCheckRejectsAnUnsignedUrl(): void
    {
        self::assertFalse($this->signer->check('https://example.com/media/pgi/wait/uploads/hero.jpg'));
    }
}
