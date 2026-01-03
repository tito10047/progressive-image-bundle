<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Tito10047\ProgressiveImageBundle\Tests\Unit\DTO;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\DTO\BreakpointAssignment;

class BreakpointAssignmentTest extends TestCase
{

	#[DataProvider('provideBreakpointStrings')]
	public function testFromString(string $input, ?string $ratio, string $expectedBreakpoint, int $expectedColumns, ?string $expectedRatio): void
    {
        $assignment = BreakpointAssignment::fromSegment($input, $ratio);

        $this->assertSame($expectedBreakpoint, $assignment->breakpoint);
        $this->assertSame($expectedColumns, $assignment->columns);
        $this->assertSame($expectedRatio, $assignment->ratio);
    }

    public static function provideBreakpointStrings(): array
    {
        return [
            'full format' => ['lg-4@landscape', null, 'lg', 4, 'landscape'],
            'without ratio' => ['lg-4', null, 'lg', 4, null],
            'different breakpoint' => ['xs-12', null, 'xs', 12, null],
            'different breakpoint with ratio' => ['xs-12@square', null, 'xs', 12, 'square'],
            'ratio with numbers' => ['md-6@3-2', null, 'md', 6, '3-2'],
            'uppercase' => ['XL-8@PORTRAIT', null, 'XL', 8, 'PORTRAIT'],
            'default ratio used' => ['lg-4', '16-9', 'lg', 4, '16-9'],
            'segment ratio overrides default' => ['lg-4@landscape', '16-9', 'lg', 4, 'landscape'],
        ];
    }

    public function testFromStringInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid breakpoint assignment format: "invalid"');
        BreakpointAssignment::fromSegment('invalid', null);
    }

    public function testFromStringMissingColumns(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid breakpoint assignment format: "lg"');
        BreakpointAssignment::fromSegment('lg', null);
    }

    public function testParseStrong(): void
    {
        $input = 'lg-4@landscape xs-12@square';
        $results = BreakpointAssignment::parseSegments($input, null);

        $this->assertCount(2, $results);
        
        $this->assertSame('lg', $results[0]->breakpoint);
        $this->assertSame(4, $results[0]->columns);
        $this->assertSame('landscape', $results[0]->ratio);

        $this->assertSame('xs', $results[1]->breakpoint);
        $this->assertSame(12, $results[1]->columns);
        $this->assertSame('square', $results[1]->ratio);
    }

	#[DataProvider('provideMultipleSegments')]
	public function testParseStrongWithDifferentInputs(string $input, ?string $ratio, int $expectedCount): void
    {
        $results = BreakpointAssignment::parseSegments($input, $ratio);
        $this->assertCount($expectedCount, $results);
    }

    public static function provideMultipleSegments(): array
    {
        return [
            ['lg-4@landscape xs-12', null, 2],
            ['lg-4', null, 1],
            ['lg-4@landscape', null, 1],
            ['lg-4 xs-12', '16-9', 2],
        ];
    }
}
