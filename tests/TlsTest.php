<?php

declare(strict_types=1);

namespace Thesis\Grpc;

use Amp\Cancellation;
use Amp\Socket\Certificate;
use Echos\Api\V1\EchoRequest;
use Echos\Api\V1\EchoResponse;
use Echos\Api\V1\EchoServiceClient;
use Echos\Api\V1\EchoServiceServer;
use Echos\Api\V1\EchoServiceServerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Thesis\Grpc\Client\Internal\AmphpHttpClient;
use Thesis\Grpc\Server\Internal\AmphpHttpServer;

#[CoversClass(AmphpHttpServer::class)]
#[CoversClass(AmphpHttpClient::class)]
final class TlsTest extends TestCase
{
    public function testTLS(): void
    {
        $server = new Server\Builder()
            ->withServices(new EchoServiceServerRegistry(new TlsEchoServer()))
            ->withTransportCredentials(
                new Server\TransportCredentials()
                    ->withDefaultCertificate(new Certificate(
                        self::path('server.crt'),
                        self::path('server.key'),
                    )),
            )
            ->build();

        $server->start();

        try {
            $client = new EchoServiceClient(
                new Client\Builder()
                    ->withTransportCredentials(
                        new Client\TransportCredentials()
                            ->withCaCert(self::path('ca.crt'))
                            ->withPeerName('localhost'),
                    )
                    ->build(),
            );

            $response = $client->echo(new EchoRequest('tls'));
            self::assertSame('tls', $response->sentence);
        } finally {
            $server->stop();
        }
    }

    public function testmTLS(): void
    {
        $caCert = self::path('ca.crt');

        $server = new Server\Builder()
            ->withServices(new EchoServiceServerRegistry(new TlsEchoServer()))
            ->withTransportCredentials(
                new Server\TransportCredentials()
                    ->withDefaultCertificate(new Certificate(
                        self::path('server.crt'),
                        self::path('server.key'),
                    ))
                    ->withCaCert($caCert)
                    ->withPeerName('client')
                    ->withVerifyPeer(),
            )
            ->build();

        $server->start();

        try {
            $client = new EchoServiceClient(
                new Client\Builder()
                    ->withTransportCredentials(
                        new Client\TransportCredentials()
                            ->withCaCert($caCert)
                            ->withPeerName('localhost')
                            ->withCertificate(new Certificate(
                                self::path('client.crt'),
                                self::path('client.key'),
                            )),
                    )
                    ->build(),
            );

            $response = $client->echo(new EchoRequest('mtls'));
            self::assertSame('mtls', $response->sentence);
        } finally {
            $server->stop();
        }
    }

    /**
     * @param non-empty-string $filename
     * @return non-empty-string
     */
    private static function path(string $filename): string
    {
        return __DIR__ . "/Stub/tls/{$filename}";
    }
}

/**
 * @internal
 */
final readonly class TlsEchoServer implements EchoServiceServer
{
    #[\Override]
    public function echo(EchoRequest $request, Metadata $md, Cancellation $cancellation): EchoResponse
    {
        return new EchoResponse($request->sentence);
    }
}
