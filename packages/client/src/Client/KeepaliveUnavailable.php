<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

use Thesis\Grpc\GrpcException;

/**
 * @api
 */
final class KeepaliveUnavailable extends GrpcException
{
    public function __construct()
    {
        parent::__construct('TCP keepalive requires the "sockets" extension.');
    }
}
