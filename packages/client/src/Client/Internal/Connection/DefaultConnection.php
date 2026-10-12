<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal\Connection;

use Amp\Cancellation;
use Amp\CompositeCancellation;
use Amp\DeferredCancellation;
use Amp\Http\Client\HttpException;
use Amp\NullCancellation;
use Thesis\Google\Rpc\Code;
use Thesis\Grpc\Client\EndpointResolver;
use Thesis\Grpc\Client\EndpointResolverListener;
use Thesis\Grpc\Client\Internal\Connection;
use Thesis\Grpc\Client\Internal\Http2\StreamFactory;
use Thesis\Grpc\Client\Internal\TrackingSocketConnector;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\Client\LoadBalancer;
use Thesis\Grpc\Client\LoadBalancerFactory;
use Thesis\Grpc\Client\PickContext;
use Thesis\Grpc\Client\Resolution;
use Thesis\Grpc\Client\Target;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\InvokeError;
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
        private StreamFactory $streams,
        private TrackingSocketConnector $sockets,
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
    public function createStream(
        Invoke $invoke,
        Metadata $md,
        Cancellation $cancellation,
        PickContext $pick,
    ): ClientStream {
        $cancellation = new CompositeCancellation($cancellation, $this->deferredCancellation->getCancellation());
        $error = null;

        // Like pick_first in grpc-go, an endpoint that cannot be connected to is skipped in favour of the next one.
        while (!$pick->excluded($endpoint = $this->balancer->pick($pick))) {
            $pick->exclude($endpoint);

            try {
                return $this->streams->create($invoke, $endpoint->address, $md, $cancellation);
            } catch (HttpException $e) {
                $error = $e;
            }
        }

        throw new InvokeError(Code::UNAVAILABLE, $error?->getMessage(), previous: $error);
    }

    #[\Override]
    public function close(Cancellation $cancellation = new NullCancellation()): void
    {
        $this->deferredCancellation->cancel();
        $this->sockets->close();
    }

    #[\Override]
    public function onResolve(Resolution|\Throwable $result): void
    {
        if ($result instanceof Resolution) {
            $this->balancer->refresh($result->endpoints);
        }
    }
}
