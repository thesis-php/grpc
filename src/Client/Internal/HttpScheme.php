<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal;

/**
 * @internal
 */
enum HttpScheme: string
{
    case Http = 'http';
    case Https = 'https';
}
