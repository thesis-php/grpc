<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

use Thesis\Grpc\Metadata;
use Thesis\Grpc\ServerStream;

/**
 * @api
 * @template TRequest of object
 * @template TResponse of object
 * @template-implements \IteratorAggregate<array-key, TRequest>
 */
final readonly class BidirectionalStreamChannel implements \IteratorAggregate
{
    /** Can be modified before the first message is sent. */
    public Metadata $responseHeaders;

    public Metadata $trailers;

    /**
     * @param ServerStream<TRequest, TResponse> $stream
     */
    public function __construct(
        private ServerStream $stream,
    ) {
        $this->responseHeaders = $stream->responseHeaders;
        $this->trailers = $stream->trailers;
    }

    /**
     * @no-named-arguments
     * @param TResponse ...$responses
     */
    public function send(object ...$responses): void
    {
        foreach ($responses as $response) {
            $this->stream->send($response);
        }
    }

    /**
     * @return TRequest
     */
    public function receive(): object
    {
        return $this->stream->receive();
    }

    public function close(): void
    {
        $this->stream->close();
    }

    #[\Override]
    public function getIterator(): \Traversable
    {
        return $this->stream->getIterator();
    }
}
