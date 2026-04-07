<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Target::class)]
#[CoversClass(InvalidTarget::class)]
final class TargetTest extends TestCase
{
    /**
     * @param non-empty-string $input
     */
    #[DataProvider('provideParseTargetCases')]
    public function testParseTarget(string $input, Target $expected): void
    {
        self::assertEquals($expected, Target::parse($input));
    }

    /**
     * @return iterable<string, array{string, Target}>
     */
    public static function provideParseTargetCases(): iterable
    {
        yield 'dns:host:port' => [
            'dns:myhost:50051',
            new Target(Scheme::Dns, [new TargetAddress('myhost', 50_051)]),
        ];

        yield 'dns:///host:port' => [
            'dns:///myhost:50051',
            new Target(Scheme::Dns, [new TargetAddress('myhost', 50_051)]),
        ];

        yield 'dns://authority/host:port' => [
            'dns://authority:53/myhost:50051',
            new Target(Scheme::Dns, [new TargetAddress('myhost', 50_051)], 'authority:53'),
        ];

        yield 'dns://authority/host:port without authority port' => [
            'dns://authority/myhost:50051',
            new Target(Scheme::Dns, [new TargetAddress('myhost', 50_051)], 'authority'),
        ];

        yield 'dns:///ipv6 with brackets' => [
            'dns:///[2001:db8:85a3:8d3:1319:8a2e:370:7348]:443',
            new Target(Scheme::Dns, [new TargetAddress('[2001:db8:85a3:8d3:1319:8a2e:370:7348]', 443)]),
        ];

        yield 'dns:///ipv6 percent-encoded brackets' => [
            'dns:///%5B2001:db8:85a3:8d3:1319:8a2e:370:7348%5D:443',
            new Target(Scheme::Dns, [new TargetAddress('[2001:db8:85a3:8d3:1319:8a2e:370:7348]', 443)]),
        ];

        yield 'ipv4:single address' => [
            'ipv4:192.168.0.1:50051',
            new Target(Scheme::Ipv4, [new TargetAddress('192.168.0.1', 50_051)]),
        ];

        yield 'ipv4:multiple addresses' => [
            'ipv4:192.168.0.1:50051,192.168.0.2:50052',
            new Target(Scheme::Ipv4, [
                new TargetAddress('192.168.0.1', 50_051),
                new TargetAddress('192.168.0.2', 50_052),
            ]),
        ];

        yield 'ipv4:multiple addresses with spaces around comma' => [
            'ipv4:192.168.0.1:50051, 192.168.0.2:50052',
            new Target(Scheme::Ipv4, [
                new TargetAddress('192.168.0.1', 50_051),
                new TargetAddress('192.168.0.2', 50_052),
            ]),
        ];

        yield 'ipv6:single address with port' => [
            'ipv6:[::1]:50051',
            new Target(Scheme::Ipv6, [new TargetAddress('[::1]', 50_051)]),
        ];

        yield 'ipv6:multiple addresses' => [
            'ipv6:[::1]:50051,[::2]:50052',
            new Target(Scheme::Ipv6, [
                new TargetAddress('[::1]', 50_051),
                new TargetAddress('[::2]', 50_052),
            ]),
        ];

        yield 'bare host:port' => [
            'myhost:50051',
            new Target(Scheme::Dns, [new TargetAddress('myhost', 50_051)]),
        ];

        yield 'bare localhost:port' => [
            'localhost:50051',
            new Target(Scheme::Dns, [new TargetAddress('localhost', 50_051)]),
        ];

        yield 'unix:///path' => [
            'unix:///var/run/grpc.sock',
            new Target(Scheme::Unix, [new TargetAddress('/var/run/grpc.sock', 0)]),
        ];

        yield 'unix:/path' => [
            'unix:/var/run/grpc.sock',
            new Target(Scheme::Unix, [new TargetAddress('/var/run/grpc.sock', 0)]),
        ];

        yield 'unix:///tmp/test.sock' => [
            'unix:///tmp/test.sock',
            new Target(Scheme::Unix, [new TargetAddress('/tmp/test.sock', 0)]),
        ];

        yield 'passthrough:///host:port' => [
            'passthrough:///myhost:50051',
            new Target(Scheme::Passthrough, [new TargetAddress('myhost', 50_051)]),
        ];

        yield 'passthrough:host:port' => [
            'passthrough:myhost:50051',
            new Target(Scheme::Passthrough, [new TargetAddress('myhost', 50_051)]),
        ];

        yield 'bare bracketed ipv6' => [
            '[::1]:50051',
            new Target(Scheme::Dns, [new TargetAddress('[::1]', 50_051)]),
        ];
    }

    /**
     * @param non-empty-string $input
     */
    #[DataProvider('provideParseTargetThrowsCases')]
    public function testParseTargetThrows(string $input): void
    {
        $this->expectException(InvalidTarget::class);
        Target::parse($input);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideParseTargetThrowsCases(): iterable
    {
        yield 'empty string' => [''];
        yield 'uppercase dns scheme is invalid' => ['DNS:myhost:50051'];
        yield 'uppercase ipv4 scheme is invalid' => ['IPV4:192.168.0.1:50051'];
        yield 'uppercase ipv6 scheme is invalid' => ['IPV6:[::1]:50051'];
        yield 'ipv4: no address' => ['ipv4:'];
        yield 'ipv6: no address' => ['ipv6:'];
        yield 'dns:/// empty host' => ['dns:///'];
        yield 'unknown scheme' => ['etcd:myhost:50051'];
        yield 'ipv4: trailing comma' => ['ipv4:192.168.0.1:50051,'];
        yield 'ipv4: leading comma' => ['ipv4:,192.168.0.1:50051'];
        yield 'http scheme' => ['http://localhost:50051'];
        yield 'https scheme' => ['https://example.com:443'];
        yield 'dns://host without slash' => ['dns://myhost'];
        yield 'dns:///host/extra' => ['dns:///myhost:50051/extra'];
        yield 'dns: endpoint with spaces' => ['dns:my host:50051'];
        yield 'bare host with spaces' => ['my host:50051'];
        yield 'unix: no path' => ['unix:'];
        yield 'unix: relative path' => ['unix:relative/path.sock'];
        yield 'unix:// without absolute path' => ['unix://relative'];
        yield 'passthrough:/// empty host' => ['passthrough:///'];
        yield 'passthrough: no address' => ['passthrough:'];
        yield 'dns: host without port' => ['dns:myhost'];
        yield 'dns:///host without port' => ['dns:///myhost'];
        yield 'ipv4: address without port' => ['ipv4:10.0.0.1'];
        yield 'ipv6: address without port' => ['ipv6:::1'];
        yield 'ipv6: bracketed without port' => ['ipv6:[::1]'];
        yield 'bare host without port' => ['myhost'];
        yield 'passthrough: host without port' => ['passthrough:myhost'];
        yield 'port out of range' => ['dns:myhost:99999'];
    }
}
