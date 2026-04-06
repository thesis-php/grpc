<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\LoadBalancer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thesis\Grpc\Client\Address;
use Thesis\Grpc\Client\Endpoint;

#[CoversClass(PickFirst::class)]
#[CoversClass(PickFirstFactory::class)]
final class PickFirstTest extends TestCase
{
    /**
     * @param non-empty-list<Endpoint> $endpoints
     */
    #[DataProvider('providePickAlwaysReturnsSameEndpointCases')]
    public function testPickAlwaysReturnsSameEndpoint(array $endpoints): void
    {
        $balancer = new PickFirstFactory()->create($endpoints);
        $first = $balancer->pick();

        for ($i = 0; $i < 10; ++$i) {
            self::assertTrue($first->equals($balancer->pick()));
        }
    }

    /**
     * @return iterable<string, array{non-empty-list<Endpoint>}>
     */
    public static function providePickAlwaysReturnsSameEndpointCases(): iterable
    {
        yield 'single endpoint' => [
            [new Endpoint(new Address('10.0.0.1:50051'))],
        ];

        yield 'multiple endpoints' => [
            [
                new Endpoint(new Address('10.0.0.1:50051')),
                new Endpoint(new Address('10.0.0.2:50051')),
                new Endpoint(new Address('10.0.0.3:50051')),
            ],
        ];
    }

    /**
     * @param non-empty-list<Endpoint> $initial
     * @param non-empty-list<Endpoint> $refreshed
     */
    #[DataProvider('provideRefreshKeepsPinnedEndpointCases')]
    public function testRefreshKeepsPinnedEndpoint(array $initial, array $refreshed): void
    {
        $balancer = new PickFirstFactory()->create($initial);
        $pinned = $balancer->pick();

        $balancer->refresh($refreshed);

        self::assertTrue($pinned->equals($balancer->pick()));
    }

    /**
     * @return iterable<string, array{non-empty-list<Endpoint>, non-empty-list<Endpoint>}>
     */
    public static function provideRefreshKeepsPinnedEndpointCases(): iterable
    {
        $a = new Endpoint(new Address('10.0.0.1:50051'));
        $b = new Endpoint(new Address('10.0.0.2:50051'));
        $c = new Endpoint(new Address('10.0.0.3:50051'));

        yield 'same list' => [
            [$a, $b], [$a, $b],
        ];

        yield 'new endpoint added' => [
            [$a, $b], [$a, $b, $c],
        ];

        yield 'order changed but pinned still present' => [
            [$a, $b, $c], [$c, $b, $a],
        ];
    }

    /**
     * @param non-empty-list<Endpoint> $initial
     * @param non-empty-list<Endpoint> $refreshed
     */
    #[DataProvider('provideRefreshSwitchesPinnedEndpointCases')]
    public function testRefreshSwitchesPinnedEndpoint(array $initial, array $refreshed): void
    {
        $balancer = new PickFirstFactory()->create($initial);
        $pinned = $balancer->pick();

        $balancer->refresh($refreshed);

        self::assertFalse($pinned->equals($balancer->pick()));
    }

    /**
     * @return iterable<string, array{non-empty-list<Endpoint>, non-empty-list<Endpoint>}>
     */
    public static function provideRefreshSwitchesPinnedEndpointCases(): iterable
    {
        $a = new Endpoint(new Address('10.0.0.1:50051'));
        $b = new Endpoint(new Address('10.0.0.2:50051'));
        $c = new Endpoint(new Address('10.0.0.3:50051'));

        yield 'pinned removed, new endpoints' => [
            [$a], [$b, $c],
        ];

        yield 'completely different list' => [
            [$a, $b], [$c],
        ];
    }
}
