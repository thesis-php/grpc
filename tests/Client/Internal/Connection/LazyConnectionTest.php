<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal\Connection;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\DeferredCancellation;
use Amp\NullCancellation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Thesis\Grpc\Client\Internal\Connection;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\Client\PickContext;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\RpcType;
use function Amp\async;
use function Amp\delay;
use function Amp\Future\await;

#[CoversClass(LazyConnection::class)]
final class LazyConnectionTest extends TestCase
{
    public function testCreatesTheConnectionOnce(): void
    {
        $created = 0;
        $connection = new LazyConnection(static function () use (&$created): Connection {
            ++$created;

            return new StubConnection();
        });

        self::createStream($connection);
        self::createStream($connection);

        self::assertSame(1, $created);
    }

    public function testConcurrentCallersShareOneAttempt(): void
    {
        $created = 0;
        $connection = new LazyConnection(static function () use (&$created): Connection {
            ++$created;
            delay(0.01);

            return new StubConnection();
        });

        await([
            async(self::createStream(...), $connection),
            async(self::createStream(...), $connection),
        ]);

        self::assertSame(1, $created);
    }

    public function testRetriesAfterAFailedAttempt(): void
    {
        $attempts = 0;
        $connection = new LazyConnection(static function () use (&$attempts): Connection {
            if (++$attempts === 1) {
                throw new \RuntimeException('Resolution failed.');
            }

            return new StubConnection();
        });

        try {
            self::createStream($connection);
            self::fail('Expected the first attempt to fail.');
        } catch (\RuntimeException $e) {
            self::assertSame('Resolution failed.', $e->getMessage());
        }

        self::createStream($connection);

        self::assertSame(2, $attempts);
    }

    public function testCancelledWaitDoesNotAbandonTheAttempt(): void
    {
        $attempts = 0;
        $connection = new LazyConnection(static function () use (&$attempts): Connection {
            ++$attempts;
            delay(0.02);

            return new StubConnection();
        });

        $cancellation = new DeferredCancellation();
        $waiter = async(self::createStream(...), $connection, $cancellation->getCancellation());
        delay(0.005);
        $cancellation->cancel();

        try {
            $waiter->await();
            self::fail('Expected the wait to be cancelled.');
        } catch (CancelledException) {
        }

        self::createStream($connection);

        self::assertSame(1, $attempts);
    }

    public function testClosesTheCreatedConnection(): void
    {
        $stub = new StubConnection();
        $connection = new LazyConnection(static fn(): Connection => $stub);

        self::createStream($connection);
        $connection->close();

        self::assertTrue($stub->closed);
    }

    public function testCloseIgnoresAFailedAttempt(): void
    {
        $connection = new LazyConnection(static function (): Connection {
            delay(0.01);

            throw new \RuntimeException('Resolution failed.');
        });

        $caller = async(self::createStream(...), $connection);
        delay(0.001);

        $connection->close();

        $this->expectException(\RuntimeException::class);
        $caller->await();
    }

    public function testCloseBeforeFirstUseIsANoOp(): void
    {
        $created = 0;
        $connection = new LazyConnection(static function () use (&$created): Connection {
            ++$created;

            return new StubConnection();
        });

        $connection->close();

        self::assertSame(0, $created);
    }

    private static function createStream(LazyConnection $connection, Cancellation $cancellation = new NullCancellation()): void
    {
        $connection->createStream(
            new Invoke('/svc/Method', \stdClass::class, RpcType::Unary),
            new Metadata(),
            $cancellation,
            new PickContext('/svc/Method', new Metadata()),
        );
    }
}
