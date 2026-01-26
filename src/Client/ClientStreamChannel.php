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
     * @no-named-arguments
     * @param TRequest ...$requests
     */
    public function send(object ...$requests): void
    {
        foreach ($requests as $request) {
            $this->stream->send($request);
        }
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
