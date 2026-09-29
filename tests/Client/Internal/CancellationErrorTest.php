<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal;

use Amp\CancelledException;
use Amp\TimeoutException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Thesis\Google\Rpc\Code;

#[CoversClass(CancellationError::class)]
final class CancellationErrorTest extends TestCase
{
    public function testExpiredDeadlineIsDeadlineExceeded(): void
    {
        $cancelled = new CancelledException(new TimeoutException('Too slow'));

        $error = CancellationError::from($cancelled);

        self::assertSame(Code::DEADLINE_EXCEEDED, $error->statusCode);
        self::assertSame('Too slow', $error->statusMessage);
        self::assertSame($cancelled, $error->getPrevious());
    }

    public function testOtherCancellationIsCancelled(): void
    {
        $cancelled = new CancelledException();

        $error = CancellationError::from($cancelled);

        self::assertSame(Code::CANCELLED, $error->statusCode);
        self::assertSame($cancelled, $error->getPrevious());
    }

    public function testFindsTheDeadlineDeeperInTheChain(): void
    {
        $cancelled = new CancelledException(new CancelledException(new TimeoutException()));

        self::assertSame(Code::DEADLINE_EXCEEDED, CancellationError::from($cancelled)->statusCode);
    }
}
