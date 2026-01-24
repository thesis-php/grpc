<?php

declare(strict_types=1);

namespace Thesis\Grpc;

use Amp\Cancellation;
use Amp\Http\Client\DelegateHttpClient;
use Amp\NullCancellation;
use Thesis\Grpc\Client\Internal\Http2;
use Thesis\Grpc\Exception\ClientStreamIsClosed;

/**
 * @api
 */
final readonly class Client
{
    private Http2\InterceptorComposer $interceptor;

    private Http2\StreamFactory $streams;

    /**
     * @param non-empty-string $host
     * @param list<Client\Interceptor> $interceptors
     */
    public function __construct(
        string $host,
        DelegateHttpClient $client,
        Encoding\Encoder $encoder,
        Compression\Compressor $compressor,
        array $interceptors = [],
    ) {
        $this->interceptor = new Http2\InterceptorComposer([
            ...$interceptors,
            new Http2\AppendControlMetadataInterceptor(
                $encoder->name(),
                $compressor->name(),
            ),
        ]);
        $this->streams = new Http2\StreamFactory(
            http: $client,
            uri: new Http2\UriFactory($host),
            encoder: $encoder,
            compressor: $compressor,
        );
    }

    /**
     * @template In of object
     * @template Out of object
     * @param In $request
     * @param Client\Invoke<In, Out> $invoke
     * @return Out
     * @throws ClientStreamIsClosed
     */
    public function invoke(
        object $request,
        Client\Invoke $invoke,
        Metadata $md = new Metadata(),
        Cancellation $cancellation = new NullCancellation(),
    ): object {
        $stream = $this->createStream(
            $invoke,
            $md,
            $cancellation,
        );

        $stream->send($request);
        $stream->close();

        return $stream->receive();
    }

    /**
     * @template In of object
     * @template Out of object
     * @param Client\Invoke<In, Out> $invoke
     * @return ClientStream<In, Out>
     */
    public function createStream(
        Client\Invoke $invoke,
        Metadata $md = new Metadata(),
        Cancellation $cancellation = new NullCancellation(),
    ): ClientStream {
        /** @var ClientStream<In, Out> */
        return $this->interceptor->intercept(
            $invoke,
            $md,
            $cancellation,
            $this->streams->create(...),
        );
    }
}
