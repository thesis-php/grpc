<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal\Http2;

use Amp\Cancellation;
use Amp\Http\Client\Connection\ConnectionPool;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\Request;
use Thesis\Grpc\Client\Address;

/**
 * The request URI carries the ":authority" of the target, so connections are pooled
 * by the endpoint address the call was picked for, passed as a request attribute.
 *
 * @internal
 */
final class EndpointConnectionPool implements ConnectionPool
{
    /** @var array<non-empty-string, ConnectionPool> */
    private array $pools = [];

    /**
     * @param \Closure(Address): ConnectionPool $createPool
     */
    public function __construct(
        private readonly \Closure $createPool,
    ) {}

    #[\Override]
    public function getStream(Request $request, Cancellation $cancellation): Stream
    {
        /** @var Address $address */
        $address = $request->getAttribute(Address::class);

        return ($this->pools[$address->value] ??= ($this->createPool)($address))->getStream($request, $cancellation);
    }
}
