<?php

declare(strict_types=1);

namespace Thesis\Grpc\Internal\Protocol;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(Frame::class)]
#[CoversFunction('Thesis\Grpc\Internal\Protocol\encodeFrame')]
#[CoversFunction('Thesis\Grpc\Internal\Protocol\decodeFrame')]
final class FrameTest extends TestCase
{
    #[TestWith([
        new Frame(true, 'compressed'),
    ])]
    #[TestWith([
        new Frame(false, 'uncompressed'),
    ])]
    public function testRoundTrip(Frame $frame): void
    {
        self::assertEquals($frame, decodeFrame(encodeFrame($frame)));
    }
}
