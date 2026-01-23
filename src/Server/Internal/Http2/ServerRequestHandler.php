<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Internal\Http2;

use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
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
use Thesis\Grpc\UnimplementedException;

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
        $this->services = self::compileServices($services);
    }

    #[\Override]
    public function handleRequest(Request $request): Response
    {
        $md = new Metadata($request->getHeaders());

        $contentType = $md->value('content-type');

        try {
            $compressor = $this->compressorFactory->compressor(
                $md->compression(Compressor::DEFAULT_COMPRESSION),
            );

            $encoder = $this->encoderFactory->encoder(
                $md->encoding(Encoder::DEFAULT_ENCODING),
            );

            $path = $request->getUri()->getPath();

            if ($path === '') {
                throw new InvalidRpcMethod('Malformed method name.');
            }

            $endpoint = Endpoint::parse($path);

            $methods = $this->services[$endpoint->service] ?? throw new InvalidRpcMethod("Unknown service {$endpoint->service}.");
            $rpc = $methods[$endpoint->method] ?? throw new InvalidRpcMethod("Unknown method {$endpoint->method} for service {$endpoint->service}.");

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

            EventLoop::queue($rpc->handler->handle(...), $stream);

            return $response;
        } catch (UnimplementedException $e) {
            $md = new Metadata()
                ->withKey(Metadata\StatusCode::UNIMPLEMENTED)
                ->withKey(new Metadata\StatusMessage($e->getMessage()));

            if ($contentType !== null) {
                $md = $md->withKey(new Metadata\ContentType($contentType));
            }

            return new Response(
                headers: $md->kv,
            );
        }
    }

    /**
     * @param list<Service> $services
     * @return array<non-empty-string, non-empty-array<non-empty-string, Rpc>>
     */
    private static function compileServices(array $services): array
    {
        $serviceMap = [];

        foreach ($services as $service) {
            $handlerMap = [];

            foreach ($service->handlers as $handler) {
                $handlerMap[$handler->handle->method] = $handler;
            }

            $serviceMap[$service->name] = $handlerMap;
        }

        return $serviceMap;
    }
}
