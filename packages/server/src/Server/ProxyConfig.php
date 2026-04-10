<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

use Amp\Http\Server\Middleware\ForwardedHeaderType;

/**
 * @api
 */
final readonly class ProxyConfig
{
    /**
     * @param list<non-empty-string> $trustedProxies Array of IPv4 or IPv6 addresses with an optional subnet mask
     */
    public function __construct(
        public ForwardedHeaderType $headerType,
        public array $trustedProxies,
    ) {}
}
