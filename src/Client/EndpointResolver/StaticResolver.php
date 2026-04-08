<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\EndpointResolver;

use Amp\Cancellation;
use Thesis\Grpc\Client\Address;
use Thesis\Grpc\Client\Endpoint;
use Thesis\Grpc\Client\EndpointResolver;
use Thesis\Grpc\Client\EndpointResolverListener;
use Thesis\Grpc\Client\Resolution;
use Thesis\Grpc\Client\Target;
use Thesis\Grpc\Client\TargetAddress;

/**
 * @api
 */
final readonly class StaticResolver implements EndpointResolver
{
    #[\Override]
    public function resolve(Target $target, EndpointResolverListener $listener, Cancellation $cancellation): Resolution
    {
        return new Resolution(
            array_map(
                static fn(TargetAddress $address) => new Endpoint(new Address((string) $address)),
                $target->addresses,
            ),
        );
    }
}
