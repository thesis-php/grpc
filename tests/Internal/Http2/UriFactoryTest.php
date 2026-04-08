<?php

declare(strict_types=1);

namespace Thesis\Grpc\Internal\Http2;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Thesis\Grpc\Client\Address;
use Thesis\Grpc\Client\Internal\Http2\UriFactory;
use Thesis\Grpc\Client\Internal\HttpScheme;
use Thesis\Grpc\Client\Invoke;

#[CoversClass(UriFactory::class)]
final class UriFactoryTest extends TestCase
{
    /**
     * @param non-empty-string $address
     * @param non-empty-string $method
     * @param non-empty-string $uri
     */
    #[TestWith([
        'http',
        'localhost:50051',
        'test.api.v1.EchoController/Echo',
        'http://localhost:50051/test.api.v1.EchoController/Echo',
    ])]
    #[TestWith([
        'http',
        'localhost:50051',
        '/test.api.v1.EchoController/Echo',
        'http://localhost:50051/test.api.v1.EchoController/Echo',
    ])]
    #[TestWith([
        'https',
        'localhost:50051',
        'test.api.v1.EchoController/Echo',
        'https://localhost:50051/test.api.v1.EchoController/Echo',
    ])]
    #[TestWith([
        'http',
        '192.168.0.1:50051',
        'test.api.v1.EchoController/Echo',
        'http://192.168.0.1:50051/test.api.v1.EchoController/Echo',
    ])]
    public function testCreate(
        string $scheme,
        string $address,
        string $method,
        string $uri,
    ): void {
        $factory = new UriFactory(HttpScheme::from($scheme));
        $invoke = new Invoke($method, \stdClass::class);

        self::assertSame($uri, $factory->create(new Address($address), $invoke));
    }
}
