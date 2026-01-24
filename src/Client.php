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
    /** @var list<Client\Interceptor> */
    private array $interceptors;

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
        $this->interceptors = [
            ...$interceptors,
            new Client\AppendMetadataInterceptor(
                new Metadata()->withKeys(
                    new Metadata\Control($encoder->name(), $compressor->name()),
                ),
            ),
        ];
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

        try {
            return $stream->receive();
        } catch (\Throwable) {
            dd($stream->headers());
        }
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
        $handler = array_reduce(
            array_reverse($this->interceptors),
            static fn(callable $stack, Client\Interceptor $interceptor) => static fn(
                Client\Invoke $invoke,
                Metadata $md,
                Cancellation $cancellation,
            ) => $interceptor->intercept(
                $invoke,
                $md,
                $cancellation,
                new Client\StackInterceptor($stack),
            ),
            $this->streams->create(...),
        );

        /** @var ClientStream<In, Out> */
        return $handler($invoke, $md, $cancellation);
    }
}
