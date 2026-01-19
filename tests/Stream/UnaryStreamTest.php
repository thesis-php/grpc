<?php

declare(strict_types=1);

namespace Thesis\Grpc\Stream;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UnaryStream::class)]
final class UnaryStreamTest extends TestCase
{
    public function testIterator(): void
    {
        $stream = new UnaryStream($o = new \stdClass());
        self::assertEquals([$o], iterator_to_array($stream));
    }
}
