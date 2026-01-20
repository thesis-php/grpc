<?php

declare(strict_types=1);

namespace Thesis\Grpc\Internal\Http2\Stream;

use Amp\Cancellation;
use Amp\Future;
use Amp\Http\Client\Response;
use Amp\NullCancellation;
use Amp\Pipeline;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Exception\ClientStreamIsClosed;
use Thesis\Grpc\Metadata;

/**
 * @internal
 * @template In of object
 * @template-covariant Out of object
 * @template-implements ClientStream<In, Out>
 */
final class ConcurrentClientStream implements ClientStream
{
    private ?Response $response = null;

    /** @var ?Pipeline\ConcurrentIterator<Out> */
    private ?Pipeline\ConcurrentIterator $recv = null;

    /**
     * @param Future<Response> $responseFuture
     * @param Pipeline\Queue<In> $send
     * @param \Closure(Response): Pipeline\ConcurrentIterator<Out> $decode
     */
    public function __construct(
        private readonly Future $responseFuture,
        private readonly Pipeline\Queue $send,
        private readonly \Closure $decode,
    ) {}

    #[\Override]
    public function send(mixed $message): void
    {
        try {
            $this->send->push($message);
        } catch (Pipeline\DisposedException $e) {
            throw new ClientStreamIsClosed($e->getMessage(), $e->getCode(), $e);
        }
    }

    #[\Override]
    public function receive(): mixed
    {
        $recv = $this->doGetReceiver();

        if (!$recv->continue()) {
            throw new ClientStreamIsClosed();
        }

        return $recv->getValue();
    }

    #[\Override]
    public function headers(): Metadata
    {
        return new Metadata($this->doGetResponse()->getHeaders());
    }

    #[\Override]
    public function trailers(Cancellation $cancellation = new NullCancellation()): Metadata
    {
        return new Metadata($this->doGetResponse()->getTrailers()->await($cancellation)->getHeaders());
    }

    #[\Override]
    public function close(): void
    {
        if ($this->send->isComplete()) {
            throw new ClientStreamIsClosed();
        }

        $this->send->complete();
    }

    #[\Override]
    public function getIterator(): \Traversable
    {
        return $this->doGetReceiver()->getIterator();
    }

    private function doGetResponse(): Response
    {
        return $this->response ??= $this->responseFuture->await();
    }

    /**
     * @return Pipeline\ConcurrentIterator<Out>
     */
    private function doGetReceiver(): Pipeline\ConcurrentIterator
    {
        return $this->recv ??= ($this->decode)($this->doGetResponse());
    }
}
