<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal;

use Amp\Cancellation;
use Amp\Http\Client\DelegateHttpClient;
use Amp\NullCancellation;
use Thesis\Grpc\Client;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Compression;
use Thesis\Grpc\Encoding;
use Thesis\Grpc\Metadata;
use Thesis\Protobuf\Decoder;

/**
 * @internal
 */
final readonly class AmphpHttpClient implements Client
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
        Decoder $protobuf,
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
            errors: new Http2\ErrorHandler($protobuf),
            encoder: $encoder,
            compressor: $compressor,
        );
    }

    #[\Override]
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

    #[\Override]
    public function createStream(
        Client\Invoke $invoke,
        Metadata $md = new Metadata(),
        Cancellation $cancellation = new NullCancellation(),
    ): ClientStream {
        return $this->interceptor->intercept( // @phpstan-ignore return.type
            $invoke,
            $md,
            $cancellation,
            $this->streams->create(...),
        );
    }
}
