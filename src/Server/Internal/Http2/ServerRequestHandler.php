<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Internal\Http2;

use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\NullCancellation;
use Revolt\EventLoop;
use Thesis\Grpc\Compression\Compressor;
use Thesis\Grpc\Encoding\Encoder;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Server\InvalidRpcMethod;
use Thesis\Grpc\Server\MessageCompressorFactory;
use Thesis\Grpc\Server\MessageEncoderFactory;
use Thesis\Grpc\Server\Rpc;
use Thesis\Grpc\Server\Service;
use Thesis\Grpc\ServerStream;

/**
 * @internal
 */
final readonly class ServerRequestHandler implements RequestHandler
{
    /** @var array<non-empty-string, non-empty-array<non-empty-string, Rpc>> */
    private array $services;

    /**
     * @param list<Service> $services
     */
    public function __construct(
        private MessageEncoderFactory $encoderFactory,
        private MessageCompressorFactory $compressorFactory,
        array $services,
    ) {
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

    #[\Override]
    public function handleRequest(Request $request): Response
    {
        $md = new Metadata($request->getHeaders());

        $compressor = $this->compressorFactory->compressor(
            $md->compression(Compressor::DEFAULT_COMPRESSION),
        );

        $encoder = $this->encoderFactory->encoder(
            $md->encoding(Encoder::DEFAULT_ENCODING),
        );

        $path = $request->getUri()->getPath();

        if ($path === '' || $path === '/') {
            throw new InvalidRpcMethod("Malformed method name: {$path}");
        }

        $endpoint = Endpoint::parse($path);

        $methods = $this->services[$endpoint->service] ?? throw new InvalidRpcMethod("Unknown service {$endpoint->service}");
        $rpc = $methods[$endpoint->method] ?? throw new InvalidRpcMethod("Unknown method {$endpoint->method} for service {$endpoint->service}");

        $streams = new StreamFactory(
            $encoder,
            $compressor,
        );

        $response = new Response();

        /** @var ServerStream<object, object> $stream */
        $stream = $streams->create(
            $rpc->handle,
            $request,
            $response,
        );

        $cancellation = new NullCancellation();

        EventLoop::queue($rpc->handler->handle(...), $stream, $cancellation);

        return $response;
    }
}
