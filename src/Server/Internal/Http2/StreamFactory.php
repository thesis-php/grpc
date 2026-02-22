<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Internal\Http2;

use Amp\ByteStream\ReadableIterableStream;
use Amp\DeferredFuture;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Http\Server\Trailers;
use Amp\Pipeline;
use Thesis\Grpc\Compression\Compressor;
use Thesis\Grpc\Encoding\Encoder;
use Thesis\Grpc\Internal\Http2\StreamCodec;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Server\Handle;
use Thesis\Grpc\ServerStream;

/**
 * @internal
 */
final readonly class StreamFactory
{
    private StreamCodec $codec;

    public function __construct(
        Encoder $encoder,
        Compressor $compressor,
    ) {
        $this->codec = new StreamCodec(
            $encoder,
            $compressor,
        );
    }

    /**
     * @param Handle<*> $handle
     * @return ServerStream<*, *>
     */
    public function create(
        Handle $handle,
        Request $request,
        Response $response,
    ): ServerStream {
        /** @var Pipeline\Queue<object> $send */
        $send = new Pipeline\Queue();

        /** @var DeferredFuture<array<non-empty-string, list<string>>> $trailers */
        $trailers = new DeferredFuture();

        $response->setTrailers(new Trailers($trailers->getFuture()));
        $response->setBody(new ReadableIterableStream(
            $this->codec->encode($send->iterate()),
        ));

        return new ConcurrentServerStream(
            new Metadata($request->getHeaders()),
            $this->codec->decode($request->getBody(), $handle->type),
            $send,
            $trailers,
        );
    }
}
