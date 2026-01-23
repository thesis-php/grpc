<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

use Amp\Socket\ServerTlsContext;

/**
 * @api
 */
interface TransportCredentials
{
    public function createServerContext(): ServerTlsContext;
}
