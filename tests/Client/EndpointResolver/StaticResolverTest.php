<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\EndpointResolver;

use Amp\NullCancellation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thesis\Grpc\Client\Address;
use Thesis\Grpc\Client\Endpoint;
use Thesis\Grpc\Client\EndpointResolverListener;
use Thesis\Grpc\Client\Scheme;
use Thesis\Grpc\Client\Target;
use Thesis\Grpc\Client\TargetAddress;

#[CoversClass(StaticResolver::class)]
final class StaticResolverTest extends TestCase
{
    /**
     * @param non-empty-list<Endpoint> $endpoints
     */
    #[DataProvider('provideResolveCases')]
    public function testResolve(Target $target, array $endpoints): void
    {
        $resolver = new StaticResolver();
        $listener = $this->createMock(EndpointResolverListener::class);
        $listener
            ->expects(self::never())
            ->method('onResolve');

        $resolution = $resolver->resolve($target, $listener, new NullCancellation());

        self::assertEquals($endpoints, $resolution->endpoints);
    }

    /**
     * @return iterable<string, array{Target, non-empty-list<Endpoint>}>
     */
    public static function provideResolveCases(): iterable
    {
        yield 'ipv4: single address' => [
            new Target(Scheme::Ipv4, [new TargetAddress('192.168.0.1', 50_051)]),
            [new Endpoint(new Address('192.168.0.1:50051'))],
        ];

        yield 'ipv4: multiple addresses' => [
            new Target(Scheme::Ipv4, [
                new TargetAddress('192.168.0.1', 50_051),
                new TargetAddress('192.168.0.2', 50_052),
            ]),
            [
                new Endpoint(new Address('192.168.0.1:50051')),
                new Endpoint(new Address('192.168.0.2:50052')),
            ],
        ];

        yield 'ipv6: single address' => [
            new Target(Scheme::Ipv6, [new TargetAddress('[::1]', 50_051)]),
            [new Endpoint(new Address('[::1]:50051'))],
        ];

        yield 'unix: socket path' => [
            new Target(Scheme::Unix, [new TargetAddress('/var/run/grpc.sock', 0)]),
            [new Endpoint(new Address('/var/run/grpc.sock'))],
        ];
    }
}
