<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Internal\Http2;

use Amp\Cancellation;
use Amp\Future;
use Amp\Http\HttpStatus;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Trailers;
use Amp\NullCancellation;
use Google\Rpc;
use Revolt\EventLoop;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Server\Handle;
use Thesis\Grpc\Server\Interceptor;
use Thesis\Grpc\Server\MessageCompressorFactory;
use Thesis\Grpc\Server\MessageEncoderFactory;
use Thesis\Grpc\Server\Service;
use Thesis\Grpc\ServerStream;
use Thesis\Grpc\UnimplementedException;
use Thesis\Protobuf;

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
        Protobuf\Encoder $protobuf,
        array $services,
        array $interceptors,
    ) {
        $this->router = new Router($services);
        $this->interceptor = new InterceptorComposer([
            new StreamHandleInterceptor($protobuf),
            new ParseGrpcTimeoutCancellationInterceptor(),
            ...$interceptors,
        ]);
    }

    #[\Override]
    public function handleRequest(Request $request): Response
    {
        $md = new Metadata($request->getHeaders());

        $headers = new Metadata();

        $contentType = Metadata\parseContentType($md);

        // We intentionally do not overwrite the original "Content-Type" to trigger a parsing error if the "Content-Type" was invalid.
        // At the same time, we must return a valid "Content-Type" to the client even in case of an error,
        // so we default to "application/grpc" without specifying an encoding, since we don't know it.
        $headers = $headers->withKey($contentType ?? new Metadata\ContentType());

        if ($contentType === null) {
            return new Response(status: HttpStatus::UNSUPPORTED_MEDIA_TYPE, headers: $headers->kv);
        }

        // For "grpc-encoding" header we follow the same approach as for "Content-Type": we should not specify "IDENTITY" by default for the response to avoid sending an unnecessary header.
        // However, when obtaining a compressor, we can specify IDENTITY as the default, and the factory will provide it.
        $contentEncoding = Metadata\parseContentEncoding($md);

        try {
            $compressor = $this->compressorFactory->compressor($contentEncoding->encoding ?? Metadata\ContentEncoding::GRPC_DEFAULT_COMPRESSION);
            $encoder = $this->encoderFactory->encoder($contentType->encoding ?? Metadata\ContentType::GRPC_DEFAULT_ENCODING);
            $rpc = $this->router->route($request);
        } catch (UnimplementedException $e) {
            return new Response(
                status: HttpStatus::OK,
                headers: $headers->kv,
                trailers: new Trailers(Future::complete([
                    Metadata\Status::STATUS_HEADER => (string) Rpc\Code::UNIMPLEMENTED->value,
                    Metadata\Status::MESSAGE_HEADER => $e->getMessage(),
                ])),
            );
        }

        // The "grpc-encoding" header should only be sent when a protobuf message is expected to be returned.
        // Otherwise, compression will not be applied anyway.
        if ($contentEncoding !== null) {
            $headers = $headers->withKey($contentEncoding);
        }

        /** @var array<non-empty-string, StreamFactory> $streams */
        static $streams = [];
        $factory = $streams["{$encoder->name()}{$compressor->name()}"] ??= new StreamFactory(
            $encoder,
            $compressor,
        );

        $response = new Response(status: HttpStatus::OK, headers: $headers->kv);

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
