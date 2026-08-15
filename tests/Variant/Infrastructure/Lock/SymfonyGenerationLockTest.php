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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Infrastructure\Lock;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantId;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Lock\SymfonyGenerationLock;

final class SymfonyGenerationLockTest extends TestCase
{
    private string $lockDir;

    protected function setUp(): void
    {
        $this->lockDir = sys_get_temp_dir().'/pgi-variant-lock-'.bin2hex(random_bytes(8));
        mkdir($this->lockDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->lockDir.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->lockDir);
    }

    private function makeLock(): SymfonyGenerationLock
    {
        return new SymfonyGenerationLock(new LockFactory(new FlockStore($this->lockDir)));
    }

    public function testAcquireSucceedsWhenFree(): void
    {
        $lock = $this->makeLock()->acquire(new VariantId('abc'));

        self::assertNotNull($lock);
    }

    public function testAcquireFailsWhenAnotherProcessHoldsTheSameId(): void
    {
        $id = new VariantId('abc');
        $first = $this->makeLock()->acquire($id);
        self::assertNotNull($first);

        // A second, independent SymfonyGenerationLock pointed at the same FlockStore
        // directory simulates a concurrent process/request.
        $second = $this->makeLock()->acquire($id);

        self::assertNull($second);
    }

    public function testDifferentIdsDoNotContend(): void
    {
        $a = $this->makeLock()->acquire(new VariantId('abc'));
        $b = $this->makeLock()->acquire(new VariantId('def'));

        self::assertNotNull($a);
        self::assertNotNull($b);
    }

    public function testReleaseAllowsReacquiring(): void
    {
        $id = new VariantId('abc');
        $lockPort = $this->makeLock();
        $lock = $lockPort->acquire($id);
        self::assertNotNull($lock);

        $lockPort->release($lock);

        self::assertNotNull($this->makeLock()->acquire($id));
    }
}
