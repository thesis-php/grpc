<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

use Amp\Socket\ClientTlsContext;

/**
 * @api
 */
interface TransportCredentials
{
    public function buildTlsContext(): ClientTlsContext;
}
