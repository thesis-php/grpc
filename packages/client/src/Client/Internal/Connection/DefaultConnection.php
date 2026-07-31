<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal\Connection;

use Amp\Cancellation;
use Amp\DeferredCancellation;
use Amp\NullCancellation;
use Thesis\Grpc\Client\Endpoint;
use Thesis\Grpc\Client\EndpointResolver;
use Thesis\Grpc\Client\EndpointResolverListener;
use Thesis\Grpc\Client\Internal\Connection;
use Thesis\Grpc\Client\Internal\Http2\InterceptorComposer;
use Thesis\Grpc\Client\Internal\Http2\StreamFactory;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\Client\LoadBalancer;
use Thesis\Grpc\Client\LoadBalancerFactory;
use Thesis\Grpc\Client\PickContext;
use Thesis\Grpc\Client\Resolution;
use Thesis\Grpc\Client\Retry;
use Thesis\Grpc\Client\Target;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Metadata;

/**
 * @internal
 */
final readonly class DefaultConnection implements
    Connection,
    EndpointResolverListener
{
    private LoadBalancer $balancer;

    private DeferredCancellation $deferredCancellation;

    public function __construct(
        Target $target,
        EndpointResolver $resolver,
        LoadBalancerFactory $loadBalancerFactory,
        private InterceptorComposer $interceptor,
        private StreamFactory $streams,
        private Retry $retry,
    ) {
        $this->deferredCancellation = new DeferredCancellation();

        $resolution = $resolver->resolve(
            $target,
            $this,
            $this->deferredCancellation->getCancellation(),
        );

        $this->balancer = $loadBalancerFactory->create($resolution->endpoints);
    }

    #[\Override]
    public function invoke(
        object $request,
        Invoke $invoke,
        Metadata $md = new Metadata(),
        Cancellation $cancellation = new NullCancellation(),
    ): object {
        /** @var list<Endpoint> $excluded */
        $excluded = [];

        return $this->retry->call(
            function () use (
                &$excluded,
                $request,
                $invoke,
                $md,
                $cancellation,
            ): object {
                $endpoint = $this->balancer->pick(new PickContext($invoke->method, $md, $excluded));
                $excluded[] = $endpoint;

                $stream = $this->stream($endpoint, $invoke, $md, $cancellation);
                $stream->send($request);
                $stream->close();

                return $stream->receive();
            },
            $cancellation,
        );
    }

    #[\Override]
    public function createStream(
        Invoke $invoke,
        Metadata $md = new Metadata(),
        Cancellation $cancellation = new NullCancellation(),
    ): ClientStream {
        $endpoint = $this->balancer->pick(new PickContext($invoke->method, $md));

        return $this->stream($endpoint, $invoke, $md, $cancellation);
    }

    #[\Override]
    public function close(Cancellation $cancellation = new NullCancellation()): void
    {
        $this->deferredCancellation->cancel();
    }

    #[\Override]
    public function onResolve(Resolution|\Throwable $result): void
    {
        if ($result instanceof Resolution) {
            $this->balancer->refresh($result->endpoints);
        }
    }

    /**
     * @template In of object
     * @template Out of object
     * @param Invoke<In, Out> $invoke
     * @return ClientStream<In, Out>
     */
    private function stream(
        Endpoint $endpoint,
        Invoke $invoke,
        Metadata $md,
        Cancellation $cancellation,
    ): ClientStream {
        return $this->interceptor->intercept( // @phpstan-ignore return.type
            $invoke,
            $md,
            $cancellation,
            fn(Invoke $invoke, Metadata $md, Cancellation $cancellation) => $this->streams->create(
                $invoke,
                $endpoint->address,
                $md,
                $cancellation,
            ),
        );
    }
}
