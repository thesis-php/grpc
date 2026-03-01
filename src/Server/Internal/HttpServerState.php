<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Internal;

/**
 * @internal
 */
enum HttpServerState
{
    case Serve;
    case Idle;
}
