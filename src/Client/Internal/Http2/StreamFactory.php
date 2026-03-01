<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal\Http2;

use Amp\ByteStream\ReadableIterableStream;
use Amp\Cancellation;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Client\StreamedContent;
use Amp\NullCancellation;
use Amp\Pipeline;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Compression\Compressor;
use Thesis\Grpc\Encoding\Encoder;
use Thesis\Grpc\Internal\Http2\StreamCodec;
use Thesis\Grpc\Metadata;
use function Amp\async;

/**
 * @internal
 */
final readonly class StreamFactory
{
    private StreamCodec $codec;

    public function __construct(
        private DelegateHttpClient $http,
        private UriFactory $uri,
        private ErrorHandler $errors,
        Encoder $encoder,
        Compressor $compressor,
    ) {
        $this->codec = new StreamCodec(
            $encoder,
            $compressor,
        );
    }

    /**
     * @template In of object
     * @template Out of object
     * @param Invoke<In, Out> $invoke
     * @return ClientStream<In, Out>
     */
    public function create(
        Invoke $invoke,
        Metadata $md = new Metadata(),
        Cancellation $cancellation = new NullCancellation(),
    ): ClientStream {
        /** @var Pipeline\Queue<In> $send */
        $send = new Pipeline\Queue();

        /** @var DeferredFuture<null> $deferred */
        $deferred = new DeferredFuture();

        $request = new Request(
            uri: $this->uri->create($invoke->method),
            method: 'POST',
            body: StreamedContent::fromStream(
                new ReadableIterableStream($this->codec->encode($send->iterate())),
            ),
        );
        $request->setProtocolVersions(['2']);
        $request->setHeaders($md->kv);
        $request->setTransferTimeout(0); // TODO(move to config)

        // If the program terminates after making a request, the HTTP client may not have enough time to finish sending the request body and trailers,
        // causing an error on the server side — after a certain timeout, the server will detect that the client unexpectedly closed the connection.
        // Therefore, after calling {@see ConcurrentClientStream::close()}, we must wait for a future that completes successfully
        // only after the entire body and trailers have been successfully transmitted to the server.
        $request->addEventListener(
            new RequestEventListener()
                ->onRequestBodyEnd($deferred->complete(...))  // @phpstan-ignore argument.type
                ->onRequestFailed(static function (Request $request, \Throwable $e) use ($deferred): void {
                    if (!$deferred->isComplete()) {
                        $deferred->error($e);
                    }
                }),
        );

        /** @var Future<Response> $response */
        $response = async($this->http->request(...), $request, $cancellation);

        return new ConcurrentClientStream(
            responseFuture: $response,
            send: $send,
            decode: fn(Response $response) => $this->codec->decode($response->getBody(), $invoke->type),
            errors: $this->errors,
            complete: $deferred->getFuture(),
        );
    }
}
