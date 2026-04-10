<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

use Thesis\Grpc\UnimplementedException;

/**
 * @api
 */
final class InvalidRpcMethod extends UnimplementedException {}
