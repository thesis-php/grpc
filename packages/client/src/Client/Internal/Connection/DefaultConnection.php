<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal\Connection;

use Amp\Cancellation;
use Amp\CompositeCancellation;
use Amp\DeferredCancellation;
use Amp\NullCancellation;
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
use Thesis\Grpc\Metadata;
use function Amp\delay;

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
        $endpoint = $this->balancer->pick($pick);
        $pick->exclude($endpoint);

        return $this->streams->create(
            $invoke,
            $endpoint->address,
            $md,
            new CompositeCancellation($cancellation, $this->deferredCancellation->getCancellation()),
        );
    }

    #[\Override]
    public function close(Cancellation $cancellation = new NullCancellation()): void
    {
        $this->deferredCancellation->cancel();

        // Like grpc-go, active streams end with CANCELLED rather than a transport error: the cancellation reaches them
        // through callbacks queued in the event loop, so the sockets are closed once the queue is drained.
        delay(0);

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
