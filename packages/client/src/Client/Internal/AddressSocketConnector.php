<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal;

use Amp\Cancellation;
use Amp\Socket\ConnectContext;
use Amp\Socket\Socket;
use Amp\Socket\SocketAddress;
use Amp\Socket\SocketConnector;
use Thesis\Grpc\Client\Address;

/**
 * Connects to the endpoint address rather than to the host of the request URI, which is the ":authority" of the target.
 *
 * @internal
 */
final readonly class AddressSocketConnector implements SocketConnector
{
    public function __construct(
        private SocketConnector $connector,
        private Address $address,
    ) {}

    #[\Override]
    public function connect(SocketAddress|string $uri, ?ConnectContext $context = null, ?Cancellation $cancellation = null): Socket
    {
        $address = str_starts_with($this->address->value, '/') ? "unix://{$this->address}" : "tcp://{$this->address}";

        return $this->connector->connect($address, $context, $cancellation);
    }
}
