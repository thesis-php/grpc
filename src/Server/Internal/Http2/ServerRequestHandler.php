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
use Thesis\Grpc\Server\MessageCompressorFactory;
use Thesis\Grpc\Server\MessageEncoderFactory;
use Thesis\Grpc\Server\Service;
use Thesis\Grpc\ServerStream;

/**
 * @internal
 */
final readonly class ServerRequestHandler implements RequestHandler
{
    private Router $router;

    /**
     * @param list<Service> $services
     */
    public function __construct(
        private MessageEncoderFactory $encoderFactory,
        private MessageCompressorFactory $compressorFactory,
        array $services,
    ) {
        $this->router = new Router($services);
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

        $rpc = $this->router->route($request);

        /** @var array<non-empty-string, StreamFactory> $streams */
        static $streams = [];
        $factory = $streams["{$encoder->name()}{$compressor->name()}"] ??= new StreamFactory(
            $encoder,
            $compressor,
        );

        /** @var ServerStream<object, object> $stream */
        $stream = $factory->create(
            $rpc->handle,
            $request,
            $response = new Response(),
        );

        $cancellation = new NullCancellation();

        EventLoop::queue($rpc->handler->handle(...), $stream, $cancellation);

        return $response;
    }
}
