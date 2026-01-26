<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

use Thesis\Grpc\ServerStream;

/**
 * @api
 * @template TRequest of object
 * @template TResponse of object
 */
final readonly class ServerStreamChannel
{
    /**
     * @param ServerStream<TRequest, TResponse> $stream
     */
    public function __construct(
        private ServerStream $stream,
    ) {}

    /**
     * @param TResponse $response
     */
    public function send(object $response): void
    {
        $this->stream->send($response);
    }

    public function close(): void
    {
        $this->stream->close();
    }
}
