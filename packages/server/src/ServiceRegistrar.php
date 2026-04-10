<?php

declare(strict_types=1);

namespace Thesis\Grpc;

use Thesis\Grpc\Server\ServerRunning;
use Thesis\Grpc\Server\Service;

/**
 * @api
 */
interface ServiceRegistrar
{
    /**
     * @throws ServerRunning
     */
    public function register(Service ...$services): void;

    /**
     * @return list<Service>
     */
    public function services(): array;
}
