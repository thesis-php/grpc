<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

use Google\Rpc\Code;
use Thesis\Grpc\GrpcException;

/**
 * @api
 */
final class CallError extends GrpcException
{
    /**
     * @param list<object> $details
     */
    public function __construct(
        public readonly Code $statusCode,
        public readonly ?string $statusMessage = null,
        public readonly array $details = [],
    ) {
        parent::__construct(\sprintf(
            'A grpc error with status code "%s" and message "%s" received',
            $statusCode->name,
            $statusMessage ?? $statusCode->name,
        ));
    }
}
