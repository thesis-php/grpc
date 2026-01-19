<?php

declare(strict_types=1);

namespace Thesis\Grpc\Stream;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use function Amp\async;

#[CoversClass(QueueStream::class)]
final class QueueStreamTest extends TestCase
{
    public function testIterator(): void
    {
        /** @var QueueStream<string> $stream */
        $stream = new QueueStream();

        $future = async(static function () use ($stream): void {
            for ($i = 0; $i < 5; ++$i) {
                $stream->push("req#{$i}");
            }

            $stream->complete();
        });

        $messages = [];
        foreach ($stream as $message) {
            $messages[] = $message;
        }

        $future->await();

        self::assertCount(5, $messages);
        self::assertSame(array_map(static fn(int $n) => "req#{$n}", range(0, 4)), $messages);
    }
}
