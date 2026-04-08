<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\EndpointResolver;

use Amp\Cache\Cache;
use Amp\Cancellation;
use Amp\CancelledException;
use Amp\Dns\DnsConfig;
use Amp\Dns\DnsRecord;
use Amp\Dns\DnsResolver as AmphpDnsResolver;
use Amp\Dns\Rfc1035StubDnsResolver;
use Amp\Dns\StaticDnsConfigLoader;
use Revolt\EventLoop;
use Thesis\Grpc\Client\Address;
use Thesis\Grpc\Client\Endpoint;
use Thesis\Grpc\Client\EndpointResolver;
use Thesis\Grpc\Client\EndpointResolverListener;
use Thesis\Grpc\Client\Resolution;
use Thesis\Grpc\Client\Target;

/**
 * @api
 */
final readonly class DnsResolver implements EndpointResolver
{
    private const float MIN_RESOLVE_INTERVAL = 30;
    private const float MAX_RESOLVE_INTERVAL = 300;

    /**
     * @param ?Cache<mixed> $cache
     */
    public function __construct(
        private ?AmphpDnsResolver $dns = null,
        private ?Cache $cache = null,
        private float $minResolveInterval = self::MIN_RESOLVE_INTERVAL,
        private float $maxResolveInterval = self::MAX_RESOLVE_INTERVAL,
    ) {}

    #[\Override]
    public function resolve(
        Target $target,
        EndpointResolverListener $listener,
        Cancellation $cancellation,
    ): Resolution {
        $resolver = $this->dns ?? $this->configureResolver($target);

        $result = $this->resolveNow(
            $resolver,
            $target,
            $cancellation,
        );

        $resolveTTL = $this->computeResolveTTL($result);

        if ($resolveTTL > 0) {
            EventLoop::queue(
                $this->resolveLater(...),
                $resolver,
                $listener,
                $target,
                $resolveTTL,
                $cancellation,
            );
        }

        return $result->resolution;
    }

    private function resolveNow(
        AmphpDnsResolver $resolver,
        Target $target,
        Cancellation $cancellation,
    ): ResolveResult {
        $endpoints = [];
        $ttl = null;

        foreach ($target->addresses as $address) {
            $records = $resolver->resolve($address->host, cancellation: $cancellation);

            foreach ($records as $record) {
                $ip = $record->getValue();

                if ($record->getType() === DnsRecord::AAAA) {
                    $ip = "[{$ip}]";
                }

                $endpoints[] = new Endpoint(
                    new Address("{$ip}:{$address->port}"),
                );

                if ($record->getTtl() !== null) {
                    $ttl = min($record->getTtl(), $ttl ?? \PHP_INT_MAX);
                }
            }
        }

        return new ResolveResult(
            new Resolution($endpoints),
            $ttl,
        );
    }

    private function resolveLater(
        AmphpDnsResolver $resolver,
        EndpointResolverListener $listener,
        Target $target,
        float $ttl,
        Cancellation $cancellation,
    ): void {
        while (true) {
            $suspension = EventLoop::getSuspension();
            $timerId = EventLoop::delay($ttl, $suspension->resume(...));
            $cancellationId = $cancellation->subscribe($suspension->throw(...));

            try {
                $suspension->suspend();

                $result = $this->resolveNow($resolver, $target, $cancellation);
                $listener->onResolve($result->resolution);

                $ttl = $this->computeResolveTTL($result);

                if ($ttl <= 0) {
                    return;
                }
            } catch (CancelledException) {
                return;
            } catch (\Throwable $e) {
                $listener->onResolve($e);
            } finally {
                EventLoop::cancel($timerId);
                $cancellation->unsubscribe($cancellationId);
            }
        }
    }

    private function configureResolver(Target $target): AmphpDnsResolver
    {
        $configLoader = null;
        if ($target->authority !== null) {
            $configLoader = new StaticDnsConfigLoader(
                new DnsConfig([$target->authority]),
            );
        }

        return new Rfc1035StubDnsResolver(
            $this->cache,
            $configLoader,
        );
    }

    private function computeResolveTTL(ResolveResult $result): float
    {
        return max(
            $this->minResolveInterval,
            min($this->maxResolveInterval, $result->ttl ?? $this->minResolveInterval),
        );
    }
}

final readonly class ResolveResult
{
    public function __construct(
        public Resolution $resolution,
        public ?float $ttl = null,
    ) {}
}
