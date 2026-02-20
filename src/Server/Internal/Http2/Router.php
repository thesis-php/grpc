<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Internal\Http2;

use Amp\Http\Server\Request;
use Thesis\Grpc\Server\InvalidRpcMethod;
use Thesis\Grpc\Server\Rpc;
use Thesis\Grpc\Server\Service;

/**
 * @internal
 */
final readonly class Router
{
    /** @var array<non-empty-string, non-empty-array<non-empty-string, Rpc>> */
    private array $services;

    /**
     * @param list<Service> $services
     */
    public function __construct(array $services)
    {
        $this->services = array_combine(
            array_map(static fn(Service $service) => $service->name, $services),
            array_map(
                static fn(Service $service) => array_combine(
                    array_map(static fn(Rpc $rpc) => $rpc->handle->method, $service->handlers),
                    $service->handlers,
                ),
                $services,
            ),
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

        $rpc = $this->services[$endpoint->service] ?? throw new InvalidRpcMethod("Unknown service {$endpoint->service}");

        return $rpc[$endpoint->method] ?? throw new InvalidRpcMethod("Unknown method {$endpoint->method} for service {$endpoint->service}");
    }
}
