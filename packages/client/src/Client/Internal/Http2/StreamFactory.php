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
use Thesis\Google\Rpc\Code;
use Thesis\Grpc\Client\Address;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Compression\CompressionUnavailable;
use Thesis\Grpc\Compression\Compressor;
use Thesis\Grpc\Encoding\Encoder;
use Thesis\Grpc\Internal\Http2;
use Thesis\Grpc\InvokeError;
use Thesis\Grpc\Metadata;
use function Amp\async;

/**
 * @internal
 */
final readonly class StreamFactory
{
    private Http2\StreamCodec $codec;

    /**
     * @param positive-int $maxReceiveMessageSize
     * @param list<Compressor> $compressors
     */
    public function __construct(
        private DelegateHttpClient $http,
        private UriFactory $uri,
        private ErrorHandler $errors,
        private float $transferTimeout,
        private float $inactivityTimeout,
        Encoder $encoder,
        Compressor $compressor,
        int $maxReceiveMessageSize,
        array $compressors,
    ) {
        $this->codec = new Http2\StreamCodec(
            $encoder,
            $compressor,
            $maxReceiveMessageSize,
            $compressors,
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
        Address $address,
        Metadata $md = new Metadata(),
        Cancellation $cancellation = new NullCancellation(),
    ): ClientStream {
        /** @var Pipeline\Queue<In> $send */
        $send = new Pipeline\Queue();

        /** @var DeferredFuture<null> $deferred */
        $deferred = new DeferredFuture();

        $request = new Request(
            uri: $this->uri->create($address, $invoke),
            method: 'POST',
            body: StreamedContent::fromStream(
                new ReadableIterableStream($this->codec->encode($send->iterate(), $cancellation)),
            ),
        );
        $request->setProtocolVersions(['2']);
        $request->setHeaders(Http2\encodeMetadata($md));
        $request->setTransferTimeout($this->transferTimeout);
        $request->setInactivityTimeout($this->inactivityTimeout);
        // gRPC limits the size of a single message, not the stream, see {@see StreamCodec}.
        $request->setBodySizeLimit(\PHP_INT_MAX);

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
            decode: function (Response $response) use ($invoke, $cancellation): Pipeline\ConcurrentIterator {
                try {
                    return $this->codec->decode(
                        $response->getBody(),
                        $invoke->output,
                        $cancellation,
                        Metadata\parseContentEncoding(new Metadata($response->getHeaders()))->encoding ?? Metadata\ContentEncoding::GRPC_DEFAULT_COMPRESSION,
                    );
                } catch (CompressionUnavailable $e) {
                    throw new InvokeError(Code::INTERNAL, $e->getMessage(), previous: $e);
                }
            },
            errors: $this->errors,
            complete: $deferred->getFuture(),
        );
    }
}
