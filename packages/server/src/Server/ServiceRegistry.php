<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

/**
 * @api
 */
interface ServiceRegistry
{
    /**
     * @return iterable<array-key, Service>
     */
    public function services(): iterable;
}
