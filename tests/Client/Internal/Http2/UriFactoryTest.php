<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Internal\Http2;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Thesis\Grpc\Internal\Http2\UriFactory;

#[CoversClass(UriFactory::class)]
final class UriFactoryTest extends TestCase
{
    /**
     * @param non-empty-string $host
     * @param non-empty-string $method
     * @param non-empty-string $uri
     */
    #[TestWith([
        'localhost:50051',
        'test.api.v1.EchoController/Echo',
        'localhost:50051/test.api.v1.EchoController/Echo',
    ])]
    #[TestWith([
        'localhost:50051',
        '/test.api.v1.EchoController/Echo',
        'localhost:50051/test.api.v1.EchoController/Echo',
    ])]
    #[TestWith([
        'localhost:50051/',
        '/test.api.v1.EchoController/Echo',
        'localhost:50051/test.api.v1.EchoController/Echo',
    ])]
    #[TestWith([
        'localhost:50051/',
        'test.api.v1.EchoController/Echo',
        'localhost:50051/test.api.v1.EchoController/Echo',
    ])]
    public function testCreate(
        string $host,
        string $method,
        string $uri,
    ): void {
        self::assertSame($uri, new UriFactory($host)->create($method));
    }
}
