<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

use Thesis\Grpc\ClientStream;

/**
 * @api
 * @template TRequest of object
 * @template TResponse of object
 */
final readonly class ClientStreamChannel
{
    /**
     * @param ClientStream<TRequest, TResponse> $stream
     */
    public function __construct(
        private ClientStream $stream,
    ) {}

    /**
     * @param TRequest $request
     */
    public function send(object $request): void
    {
        $this->stream->send($request);
    }

    /**
     * @return TResponse
     */
    public function close(): object
    {
        $this->stream->close();

        return $this->stream->receive();
    }
}
