<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Internal\Http2;

use Amp\Http\Server\Request;
use Thesis\Grpc\Server\InvalidRpcMethod;
use Thesis\Grpc\Server\Rpc;
use Thesis\Grpc\Server\Service;

/**
 * @internal
 * @template-implements \IteratorAggregate<array-key, Service>
 */
final class Router implements \IteratorAggregate
{
    /** @var array<non-empty-string, non-empty-array<non-empty-string, Rpc>> */
    private array $index = [];

    /** @var list<Service> */
    private array $services = [];

    public function addService(Service $service): void
    {
        $this->services[] = $service;
        $this->index[$service->name] = array_combine(
            array_map(
                static fn(Rpc $rpc) => $rpc->handle->method,
                $service->handlers,
            ),
            $service->handlers,
        );
    }

    /**
     * @throws InvalidRpcMethod
     */
    public function route(Request $request): Rpc
    {
        $path = $request->getUri()->getPath();

        if ($path === '' || $path === '/') {
            throw new InvalidRpcMethod("Malformed method name: {$path}");
        }

        $endpoint = Endpoint::parse($path);

        $rpc = $this->index[$endpoint->service] ?? throw new InvalidRpcMethod("Unknown service {$endpoint->service}");

        return $rpc[$endpoint->method] ?? throw new InvalidRpcMethod("Unknown method {$endpoint->method} for service {$endpoint->service}");
    }

    #[\Override]
    public function getIterator(): \Traversable
    {
        yield from $this->services;
    }
}
