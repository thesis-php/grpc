<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\EndpointResolver;

use Amp\DeferredCancellation;
use Amp\Dns\DnsException;
use Amp\Dns\DnsRecord;
use Amp\Dns\DnsResolver as AmphpDnsResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thesis\Grpc\Client\Address;
use Thesis\Grpc\Client\Endpoint;
use Thesis\Grpc\Client\EndpointResolverListener;
use Thesis\Grpc\Client\Resolution;
use Thesis\Grpc\Client\Scheme;
use Thesis\Grpc\Client\Target;
use Thesis\Grpc\Client\TargetAddress;
use function Amp\delay;

#[CoversClass(DnsResolver::class)]
final class DnsResolverTest extends TestCase
{
    /**
     * @param non-empty-list<DnsRecord> $records
     * @param non-empty-list<Endpoint> $endpoints
     */
    #[DataProvider('provideResolveCases')]
    public function testResolve(Target $target, array $records, array $endpoints): void
    {
        $deferredCancellation = new DeferredCancellation();

        $dnsResolver = $this->createMock(AmphpDnsResolver::class);
        $dnsResolver
            ->expects(self::once())
            ->method('resolve')
            ->willReturn($records);

        $listener = $this->createMock(EndpointResolverListener::class);
        $listener
            ->expects(self::never())
            ->method('onResolve');

        $resolver = new DnsResolver($dnsResolver);
        $resolution = $resolver->resolve($target, $listener, $deferredCancellation->getCancellation());
        $deferredCancellation->cancel();

        self::assertEquals($endpoints, $resolution->endpoints);
    }

    /**
     * @return iterable<string, array{Target, non-empty-list<DnsRecord>, non-empty-list<Endpoint>}>
     */
    public static function provideResolveCases(): iterable
    {
        yield 'single A record' => [
            new Target(Scheme::Dns, [new TargetAddress('myhost', 50_051)], 'myhost:50051'),
            [new DnsRecord('192.168.0.1', DnsRecord::A, 300)],
            [new Endpoint(new Address('192.168.0.1:50051'))],
        ];

        yield 'multiple A records' => [
            new Target(Scheme::Dns, [new TargetAddress('myhost', 50_051)], 'myhost:50051'),
            [
                new DnsRecord('192.168.0.1', DnsRecord::A, 300),
                new DnsRecord('192.168.0.2', DnsRecord::A, 300),
            ],
            [
                new Endpoint(new Address('192.168.0.1:50051')),
                new Endpoint(new Address('192.168.0.2:50051')),
            ],
        ];

        yield 'AAAA record wraps in brackets' => [
            new Target(Scheme::Dns, [new TargetAddress('myhost', 50_051)], 'myhost:50051'),
            [new DnsRecord('::1', DnsRecord::AAAA, 300)],
            [new Endpoint(new Address('[::1]:50051'))],
        ];

        yield 'mixed A and AAAA records' => [
            new Target(Scheme::Dns, [new TargetAddress('myhost', 50_051)], 'myhost:50051'),
            [
                new DnsRecord('192.168.0.1', DnsRecord::A, 300),
                new DnsRecord('::1', DnsRecord::AAAA, 300),
            ],
            [
                new Endpoint(new Address('192.168.0.1:50051')),
                new Endpoint(new Address('[::1]:50051')),
            ],
        ];
    }

    public function testResolveListener(): void
    {
        $deferredCancellation = new DeferredCancellation();

        $dnsResolver = $this->createMock(AmphpDnsResolver::class);
        $dnsResolver
            ->expects(self::atLeastOnce())
            ->method('resolve')
            ->willReturn(
                [new DnsRecord('192.168.0.1', DnsRecord::A, 1)],
            );

        $listener = $this->createMock(EndpointResolverListener::class);
        $listener
            ->expects(self::atLeastOnce())
            ->method('onResolve')
            ->with(
                self::isInstanceOf(Resolution::class),
            );

        $resolver = new DnsResolver($dnsResolver, minResolveInterval: 0.1, maxResolveInterval: 0.1);
        $resolver->resolve(
            new Target(Scheme::Dns, [new TargetAddress('myhost', 50_051)], 'myhost:50051'),
            $listener,
            $deferredCancellation->getCancellation(),
        );

        delay(0.25);
        $deferredCancellation->cancel();
    }

    public function testResolveStopOnCancellation(): void
    {
        $target = new Target(Scheme::Dns, [new TargetAddress('myhost', 50_051)], 'myhost:50051');
        $deferredCancellation = new DeferredCancellation();

        $dnsResolver = self::createStub(AmphpDnsResolver::class);
        $dnsResolver->method('resolve')->willReturn(
            [new DnsRecord('192.168.0.1', DnsRecord::A, 300)],
        );

        $listener = $this->createMock(EndpointResolverListener::class);
        $listener
            ->expects(self::never())
            ->method('onResolve');

        $resolver = new DnsResolver($dnsResolver, minResolveInterval: 0.5, maxResolveInterval: 0.5);
        $resolver->resolve($target, $listener, $deferredCancellation->getCancellation());

        $deferredCancellation->cancel();
        delay(0.7);
    }

    public function testResolveThrows(): void
    {
        $target = new Target(Scheme::Dns, [new TargetAddress('myhost', 50_051)], 'myhost:50051');
        $deferredCancellation = new DeferredCancellation();

        $dnsResolver = $this->createMock(AmphpDnsResolver::class);
        $dnsResolver
            ->expects(self::atLeastOnce())
            ->method('resolve')
            ->willReturnOnConsecutiveCalls(
                [new DnsRecord('192.168.0.1', DnsRecord::A, 1)],
                self::throwException(new DnsException('DNS failed')),
            );

        $listener = $this->createMock(EndpointResolverListener::class);
        $listener
            ->expects(self::atLeastOnce())
            ->method('onResolve')
            ->with(
                self::isInstanceOf(\Throwable::class),
            );

        $resolver = new DnsResolver($dnsResolver, minResolveInterval: 0.1, maxResolveInterval: 0.1);
        $resolver->resolve($target, $listener, $deferredCancellation->getCancellation());

        delay(0.25);
        $deferredCancellation->cancel();
    }
}
