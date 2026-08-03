<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

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
    ) {}
}
