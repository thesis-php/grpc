<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Internal\Http2;

use Amp\DeferredFuture;
use Amp\Pipeline;
use Thesis\Grpc\Exception\ServerStreamIsClosed;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\ServerStream;

/**
 * @internal
 * @template-covariant In of object
 * @template Out of object
 * @template-implements ServerStream<In, Out>
 */
final class ConcurrentServerStream implements ServerStream
{
    public private(set) Metadata $trailers { get => $this->trailers ??= new Metadata(); }

    /**
     * @param Pipeline\ConcurrentIterator<In> $recv
     * @param Pipeline\Queue<Out> $send
     * @param DeferredFuture<array<non-empty-string, list<string>>> $trailersFuture
     */
    public function __construct(
        public readonly Metadata $headers,
        private readonly Pipeline\ConcurrentIterator $recv,
        private readonly Pipeline\Queue $send,
        private readonly DeferredFuture $trailersFuture,
    ) {}

    #[\Override]
    public function send(object $message): void
    {
        try {
            $this->send->push($message);
        } catch (Pipeline\DisposedException $e) {
            throw new ServerStreamIsClosed($e->getMessage(), $e->getCode(), $e);
        }
    }

    #[\Override]
    public function receive(): object
    {
        if (!$this->recv->continue()) {
            throw new ServerStreamIsClosed();
        }

        return $this->recv->getValue();
    }

    #[\Override]
    public function close(): void
    {
        if ($this->send->isComplete()) {
            return;
        }

        $this->trailersFuture->complete($this->trailers->kv);
        $this->send->complete();
    }

    #[\Override]
    public function getIterator(): \Traversable
    {
        return $this->recv->getIterator();
    }
}
