<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

use Amp\Cancellation;

/**
 * @api
 */
interface EndpointResolver
{
    public function resolve(
        Target $target,
        EndpointResolverListener $listener,
        Cancellation $cancellation,
    ): Resolution;
}
