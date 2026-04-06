<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Resolver;

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
        yield 'dns:host' => [
            'dns:myhost',
            new Target(Scheme::Dns, ['myhost']),
        ];

        yield 'dns:host:port' => [
            'dns:myhost:50051',
            new Target(Scheme::Dns, ['myhost:50051']),
        ];

        yield 'dns:///host' => [
            'dns:///myhost',
            new Target(Scheme::Dns, ['myhost']),
        ];

        yield 'dns:///host:port' => [
            'dns:///myhost:50051',
            new Target(Scheme::Dns, ['myhost:50051']),
        ];

        yield 'dns://authority/host' => [
            'dns://authority/myhost',
            new Target(Scheme::Dns, ['myhost'], 'authority'),
        ];

        yield 'dns://authority:port/host:port' => [
            'dns://authority:53/myhost:50051',
            new Target(Scheme::Dns, ['myhost:50051'], 'authority:53'),
        ];

        yield 'ipv4:single address' => [
            'ipv4:192.168.0.1:50051',
            new Target(Scheme::Ipv4, ['192.168.0.1:50051']),
        ];

        yield 'ipv4:multiple addresses' => [
            'ipv4:192.168.0.1:50051,192.168.0.2:50052',
            new Target(Scheme::Ipv4, ['192.168.0.1:50051', '192.168.0.2:50052']),
        ];

        yield 'ipv4:multiple addresses with spaces around comma' => [
            'ipv4:192.168.0.1:50051, 192.168.0.2:50052',
            new Target(Scheme::Ipv4, ['192.168.0.1:50051', '192.168.0.2:50052']),
        ];

        yield 'ipv4:address without port' => [
            'ipv4:10.0.0.1',
            new Target(Scheme::Ipv4, ['10.0.0.1']),
        ];

        yield 'ipv6:single address with port' => [
            'ipv6:[::1]:50051',
            new Target(Scheme::Ipv6, ['[::1]:50051']),
        ];

        yield 'ipv6:multiple addresses' => [
            'ipv6:[::1]:50051,[::2]:50052',
            new Target(Scheme::Ipv6, ['[::1]:50051', '[::2]:50052']),
        ];

        yield 'ipv6:address without port' => [
            'ipv6:::1',
            new Target(Scheme::Ipv6, ['::1']),
        ];

        yield 'bare host' => [
            'myhost',
            new Target(Scheme::Dns, ['myhost']),
        ];

        yield 'bare host:port' => [
            'myhost:50051',
            new Target(Scheme::Dns, ['myhost:50051']),
        ];

        yield 'bare localhost:port' => [
            'localhost:50051',
            new Target(Scheme::Dns, ['localhost:50051']),
        ];

        yield 'unix:///path' => [
            'unix:///var/run/grpc.sock',
            new Target(Scheme::Unix, ['/var/run/grpc.sock']),
        ];

        yield 'unix:/path' => [
            'unix:/var/run/grpc.sock',
            new Target(Scheme::Unix, ['/var/run/grpc.sock']),
        ];

        yield 'unix:///tmp/test.sock' => [
            'unix:///tmp/test.sock',
            new Target(Scheme::Unix, ['/tmp/test.sock']),
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
        yield 'uppercase dns scheme is invalid' => ['DNS:myhost'];
        yield 'uppercase ipv4 scheme is invalid' => ['IPV4:192.168.0.1:50051'];
        yield 'uppercase ipv6 scheme is invalid' => ['IPV6:[::1]:50051'];
        yield 'ipv4: no address' => ['ipv4:'];
        yield 'ipv6: no address' => ['ipv6:'];
        yield 'dns:/// empty host' => ['dns:///'];
        yield 'unknown scheme' => ['etcd:myhost'];
        yield 'ipv4: trailing comma' => ['ipv4:192.168.0.1,'];
        yield 'ipv4: leading comma' => ['ipv4:,192.168.0.1'];
        yield 'http scheme' => ['http://localhost:50051'];
        yield 'https scheme' => ['https://example.com:443'];
        yield 'dns://host without slash' => ['dns://myhost'];
        yield 'dns:///host/extra' => ['dns:///myhost/extra'];
        yield 'dns: endpoint with spaces' => ['dns:my host'];
        yield 'bare host with spaces' => ['my host:50051'];
        yield 'bare bracketed ipv6 is invalid' => ['[::1]:50051'];
        yield 'unix: no path' => ['unix:'];
        yield 'unix: relative path' => ['unix:relative/path.sock'];
        yield 'unix:// without absolute path' => ['unix://relative'];
    }
}
