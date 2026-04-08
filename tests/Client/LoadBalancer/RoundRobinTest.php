<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\LoadBalancer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thesis\Grpc\Client\Address;
use Thesis\Grpc\Client\Endpoint;
use Thesis\Grpc\Client\PickContext;
use Thesis\Grpc\Metadata;

#[CoversClass(RoundRobin::class)]
#[CoversClass(RoundRobinFactory::class)]
final class RoundRobinTest extends TestCase
{
    /**
     * @param non-empty-list<Endpoint> $endpoints
     * @param list<Endpoint> $expectedPicks
     */
    #[DataProvider('providePickCyclesCases')]
    public function testPickCycles(array $endpoints, array $expectedPicks): void
    {
        $balancer = new RoundRobinFactory()->create($endpoints);

        foreach ($expectedPicks as $expected) {
            self::assertTrue($expected->equals($balancer->pick(self::context())));
        }
    }

    /**
     * @return iterable<string, array{non-empty-list<Endpoint>, list<Endpoint>}>
     */
    public static function providePickCyclesCases(): iterable
    {
        $a = new Endpoint(new Address('10.0.0.1:50051'));
        $b = new Endpoint(new Address('10.0.0.2:50051'));
        $c = new Endpoint(new Address('10.0.0.3:50051'));

        yield 'single endpoint' => [
            [$a],
            [$a, $a, $a],
        ];

        yield 'two endpoints' => [
            [$a, $b],
            [$a, $b, $a, $b],
        ];

        yield 'three endpoints, full cycle twice' => [
            [$a, $b, $c],
            [$a, $b, $c, $a, $b, $c],
        ];
    }

    /**
     * @param non-empty-list<Endpoint> $initial
     * @param non-empty-list<Endpoint> $refreshed
     * @param list<Endpoint> $expectedAfterRefresh
     */
    #[DataProvider('provideRefreshCases')]
    public function testRefresh(array $initial, int $picksBeforeRefresh, array $refreshed, array $expectedAfterRefresh): void
    {
        $balancer = new RoundRobinFactory()->create($initial);

        for ($i = 0; $i < $picksBeforeRefresh; ++$i) {
            $balancer->pick(self::context());
        }

        $balancer->refresh($refreshed);

        foreach ($expectedAfterRefresh as $expected) {
            self::assertTrue($expected->equals($balancer->pick(self::context())));
        }
    }

    /**
     * @return iterable<string, array{non-empty-list<Endpoint>, int, non-empty-list<Endpoint>, list<Endpoint>}>
     */
    public static function provideRefreshCases(): iterable
    {
        $a = new Endpoint(new Address('10.0.0.1:50051'));
        $b = new Endpoint(new Address('10.0.0.2:50051'));
        $c = new Endpoint(new Address('10.0.0.3:50051'));

        yield 'same list, continues rotation' => [
            [$a, $b, $c], 1,
            [$a, $b, $c],
            [$b, $c, $a],
        ];

        yield 'completely new list' => [
            [$a, $b], 0,
            [$c],
            [$c, $c, $c],
        ];

        yield 'list grew' => [
            [$a], 0,
            [$a, $b, $c],
            [$a, $b, $c],
        ];
    }

    private static function context(): PickContext
    {
        return new PickContext('/test.Service/Method', new Metadata());
    }
}
