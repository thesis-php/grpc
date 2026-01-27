<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Internal\Http2;

use Amp\Cancellation;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\NullCancellation;
use Revolt\EventLoop;
use Thesis\Grpc\Compression\Compressor;
use Thesis\Grpc\Encoding\Encoder;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Server\Handle;
use Thesis\Grpc\Server\Interceptor;
use Thesis\Grpc\Server\MessageCompressorFactory;
use Thesis\Grpc\Server\MessageEncoderFactory;
use Thesis\Grpc\Server\Service;
use Thesis\Grpc\ServerStream;
use Thesis\Grpc\UnimplementedException;

/**
 * @internal
 */
final readonly class ServerRequestHandler implements RequestHandler
{
    private Router $router;

    private InterceptorComposer $interceptor;

    /**
     * @param list<Service> $services
     * @param list<Interceptor> $interceptors
     */
    public function __construct(
        private MessageEncoderFactory $encoderFactory,
        private MessageCompressorFactory $compressorFactory,
        array $services,
        array $interceptors,
    ) {
        $this->router = new Router($services);
        $this->interceptor = new InterceptorComposer([
            new SuppressExceptionInterceptor(),
            new ParseGrpcTimeoutCancellationInterceptor(),
            ...$interceptors,
        ]);
    }

    #[\Override]
    public function handleRequest(Request $request): Response
    {
        $md = new Metadata($request->getHeaders());

        $response = new Response();

        try {
            $compressor = $this->compressorFactory->compressor(
                $md->compression(Compressor::DEFAULT_COMPRESSION),
            );

            $encoder = $this->encoderFactory->encoder(
                $md->encoding(Encoder::DEFAULT_ENCODING),
            );

            $rpc = $this->router->route($request);
        } catch (UnimplementedException $e) {
            $md = new Metadata()
                ->withKey(Metadata\StatusCode::UNIMPLEMENTED)
                ->withKey(new Metadata\StatusMessage($e->getMessage()));

            if (($contentType = $md->value('content-type')) !== null) {
                $md = $md->withKey(new Metadata\ContentType($contentType));
            }

            $response->setHeaders($md->kv);

            return $response;
        }

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
            $response,
        );

        $cancellation = new NullCancellation();

        EventLoop::queue(
            $this->interceptor->intercept(...),
            $rpc->handle,
            $md,
            $stream,
            $cancellation,
            static fn(
                Handle $handle,
                Metadata $md,
                ServerStream $stream,
                Cancellation $cancellation,
            ) => $rpc->handler->handle(
                $stream,
                $md,
                $cancellation,
            ),
        );

        return $response;
    }
}
