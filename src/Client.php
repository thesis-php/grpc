<?php

declare(strict_types=1);

namespace Thesis\Grpc;

use Amp\Cancellation;
use Amp\Http\Client\DelegateHttpClient;
use Amp\NullCancellation;
use Thesis\Grpc\Exception\ClientStreamIsClosed;
use Thesis\Grpc\Internal\Http2;

/**
 * @api
 */
final readonly class Client
{
    /** @var list<Client\Interceptor> */
    private array $interceptors;

    private Http2\Transport $transport;

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
        $this->interceptors = [
            ...$interceptors,
            new Client\AppendMetadataInterceptor(
                new Metadata()->withKeys(
                    new Metadata\Control($encoder->name(), $compressor->name()),
                ),
            ),
        ];
        $this->transport = new Http2\Transport(
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
     * @param Invoke<In, Out> $invoke
     * @return Out
     * @throws ClientStreamIsClosed
     */
    public function invoke(
        object $request,
        Invoke $invoke,
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
     * @param Invoke<In, Out> $invoke
     * @return ClientStream<In, Out>
     */
    public function createStream(
        Invoke $invoke,
        Metadata $md = new Metadata(),
        Cancellation $cancellation = new NullCancellation(),
    ): ClientStream {
        $handler = array_reduce(
            array_reverse($this->interceptors),
            static fn(callable $stack, Client\Interceptor $interceptor) => static fn(
                Invoke $invoke,
                Metadata $md,
                Cancellation $cancellation,
            ) => $interceptor->intercept(
                $invoke,
                $md,
                $cancellation,
                new Client\StackInterceptor($stack),
            ),
            $this->transport->createClientStream(...),
        );

        /** @var ClientStream<In, Out> */
        return $handler($invoke, $md, $cancellation);
    }
}
