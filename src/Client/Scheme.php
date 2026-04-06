<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

/**
 * @api
 */
enum Scheme: string
{
    case Dns = 'dns';
    case Ipv4 = 'ipv4';
    case Ipv6 = 'ipv6';
    case Unix = 'unix';
}
