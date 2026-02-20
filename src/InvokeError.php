<?php

declare(strict_types=1);

namespace Thesis\Grpc;

use Google\Rpc\Code;

/**
 * @api
 */
final class InvokeError extends GrpcException
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
            'A grpc error with status code "%s" and message "%s" occurred',
            $statusCode->name,
            $statusMessage ?? '',
        ));
    }
}
