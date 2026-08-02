<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Internal\Http2;

use Amp\Cancellation;
use Amp\CompositeCancellation;
use Amp\DeferredCancellation;
use Amp\Future;
use Amp\Http\HttpStatus;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Trailers;
use Amp\TimeoutCancellation;
use Google\Rpc;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Server\MessageCompressorFactory;
use Thesis\Grpc\Server\MessageEncoderFactory;
use Thesis\Grpc\Server\Service;
use Thesis\Grpc\Server\StreamInfo;
use Thesis\Grpc\Server\StreamInterceptor;
use Thesis\Grpc\Server\UnaryHandler;
use Thesis\Grpc\Server\UnaryInterceptor;
use Thesis\Grpc\ServerStream;
use Thesis\Grpc\ServiceRegistrar;
use Thesis\Grpc\UnimplementedException;
use Thesis\Protobuf;
use function Amp\async;

/**
 * @internal
 * @phpstan-type HandlerEntry = object{future: Future<void>, cancellation: DeferredCancellation}
 */
final class ServerRequestHandler implements
    RequestHandler,
    ServiceRegistrar
{
    private readonly Router $router;

    /**
     * Always-on transport lifecycle: seeds the OK trailer, maps handler exceptions to a
     * gRPC status, and closes the stream — for every RPC type.
     */
    private readonly StreamHandleInterceptor $lifecycle;

    private readonly UnaryInterceptorComposer $unary;

    private readonly StreamInterceptorComposer $stream;

    /** @var \WeakMap<ServerStream<*, *>, HandlerEntry> */
    private \WeakMap $pending;

    /**
     * @param list<UnaryInterceptor> $unaryInterceptors
     * @param list<StreamInterceptor> $streamInterceptors
     */
    public function __construct(
        private readonly MessageEncoderFactory $encoderFactory,
        private readonly MessageCompressorFactory $compressorFactory,
        Protobuf\Encoder $protobuf,
        array $unaryInterceptors,
        array $streamInterceptors,
    ) {
        $this->pending = new \WeakMap();
        $this->router = new Router();
        $this->lifecycle = new StreamHandleInterceptor($protobuf);
        $this->unary = new UnaryInterceptorComposer($unaryInterceptors);
        $this->stream = new StreamInterceptorComposer($streamInterceptors);
    }

    #[\Override]
    public function register(Service ...$services): void
    {
        array_walk($services, $this->router->addService(...));
    }

    #[\Override]
    public function services(): array
    {
        return iterator_to_array($this->router, preserve_keys: false);
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
        $factory = $streams["{$encoder->name()}\0{$compressor->name()}"] ??= new StreamFactory(
            $encoder,
            $compressor,
        );

        $response = new Response(status: HttpStatus::OK, headers: $headers->kv);

        $cancellation = new DeferredCancellation();

        $cancellations = [
            $cancellation->getCancellation(),
        ];

        if (($timeout = Metadata\parseTimeout($md)) !== null) {
            $cancellations[] = new TimeoutCancellation($timeout->toSeconds());
        }

        $streamCancellation = new CompositeCancellation(...$cancellations);

        /** @var ServerStream<object, object> $stream */
        $stream = $factory->create(
            $rpc->handle,
            $request,
            $response,
            $streamCancellation,
        );

        $info = new StreamInfo($rpc->handle->method, $rpc->type);
        $rpcHandler = $rpc->handler;

        $terminal = match (true) {
            $rpcHandler instanceof UnaryHandler => function (
                ServerStream $stream,
                StreamInfo $info,
                Metadata $md,
                Cancellation $cancellation,
            ) use ($rpcHandler): void {
                $response = $this->unary->intercept(
                    $stream->receive(),
                    $info,
                    $md,
                    $cancellation,
                    static fn(
                        object $request,
                        StreamInfo $info,
                        Metadata $md,
                        Cancellation $cancellation,
                    ): object => $rpcHandler->invoke($request, $md, $cancellation),
                );

                $stream->send($response);
                $stream->close();
            },
            default => function (
                ServerStream $stream,
                StreamInfo $info,
                Metadata $md,
                Cancellation $cancellation,
            ) use ($rpcHandler): void {
                $this->stream->intercept(
                    $stream,
                    $info,
                    $md,
                    $cancellation,
                    static fn(
                        ServerStream $stream,
                        StreamInfo $info,
                        Metadata $md,
                        Cancellation $cancellation,
                    ) => $rpcHandler->handle($stream, $md, $cancellation),
                );
            },
        };

        /** @var Future<void> $future */
        $future = async(
            $this->lifecycle->interceptStream(...),
            $stream,
            $info,
            $md,
            $streamCancellation,
            $terminal,
        );

        $future->ignore();

        $this->pending[$stream] = new readonly class ($future, $cancellation) {
            /**
             * @param Future<void> $future
             */
            public function __construct(
                public Future $future,
                public DeferredCancellation $cancellation,
            ) {}
        };

        return $response;
    }

    public function stop(Cancellation $cancellation): void
    {
        $futures = [];

        foreach ($this->pending as $entry) {
            $entry->cancellation->cancel();
            $futures[] = $entry->future;
        }

        Future\awaitAll($futures, $cancellation);
    }
}
