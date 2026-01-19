<?php

declare(strict_types=1);

namespace Thesis\Grpc\Stream;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IterableStream::class)]
final class IterableStreamTest extends TestCase
{
    public function testIterator(): void
    {
        $stream = new IterableStream($m = ['x', 'y']);
        self::assertSame($m, iterator_to_array($stream));
    }
}
