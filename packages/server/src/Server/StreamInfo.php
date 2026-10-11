<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

use Thesis\Grpc\Metadata;
use Thesis\Grpc\RpcType;

/**
 * @api
 */
final readonly class StreamInfo
{
    /**
     * @param non-empty-string $method
     */
    public function __construct(
        public string $method,
        public RpcType $type,
        /** Can be modified before the first message is sent. */
        public Metadata $responseHeaders,
        /** Can be modified before the stream is closed. */
        public Metadata $trailers,
    ) {}
}
