<?php

declare(strict_types=1);

namespace Thesis\Grpc\Exception;

use Thesis\Grpc\GrpcException;

/**
 * @api
 */
final class ServerStreamIsClosed extends GrpcException {}
