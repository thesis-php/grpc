<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

/**
 * @api
 */
interface EndpointResolverListener
{
    public function onResolve(Resolution|\Throwable $result): void;
}
