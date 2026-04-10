<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Internal\Http2;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Thesis\Grpc\Server\InvalidRpcMethod;

#[CoversClass(Endpoint::class)]
final class EndpointTest extends TestCase
{
    /**
     * @param non-empty-string $path
     */
    #[TestWith([
        '/test.api.v1.EchoController/Echo',
        new Endpoint('test.api.v1.EchoController', 'Echo'),
    ])]
    #[TestWith([
        'test.api.v1.EchoController/Echo',
        new Endpoint('test.api.v1.EchoController', 'Echo'),
    ])]
    #[TestWith([
        'EchoController/Echo',
        new Endpoint('EchoController', 'Echo'),
    ])]
    #[TestWith([
        'EchoController/Echo/',
        new Endpoint('EchoController', 'Echo'),
    ])]
    public function testParse(string $path, Endpoint $endpoint): void
    {
        self::assertEquals($endpoint, Endpoint::parse($path));
    }

    /**
     * @param non-empty-string $path
     */
    #[TestWith(['EchoController'])]
    #[TestWith(['/EchoController'])]
    #[TestWith(['EchoController/'])]
    #[TestWith(['/EchoController/'])]
    #[TestWith(['/'])]
    public function testParseMalformedPath(string $path): void
    {
        $this->expectException(InvalidRpcMethod::class);
        Endpoint::parse($path);
    }
}
