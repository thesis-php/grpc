<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Resolver;

use Thesis\Grpc\GrpcException;

/**
 * @api
 */
final class InvalidTarget extends GrpcException
{
    public function __construct(string $target)
    {
        parent::__construct(\sprintf('Invalid gRPC target: "%s".', $target));
    }
}
