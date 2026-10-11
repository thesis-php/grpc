<?php

declare(strict_types=1);

namespace Thesis\Grpc;

use Amp\Cancellation;
use Amp\Http\Server\Middleware\ClosureMiddleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Socket\Certificate;
use Amp\Socket\UnixAddress;
use Echos\Api\V1\EchoRequest;
use Echos\Api\V1\EchoResponse;
use Echos\Api\V1\EchoServiceClient;
use Echos\Api\V1\EchoServiceServer;
use Echos\Api\V1\EchoServiceServerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thesis\Grpc\Client\Internal\AddressSocketConnector;
use Thesis\Grpc\Client\Internal\Http2\EndpointConnectionPool;

#[CoversClass(Client\Builder::class)]
#[CoversClass(EndpointConnectionPool::class)]
#[CoversClass(AddressSocketConnector::class)]
final class AuthorityTest extends TestCase
{
    private const string ADDRESS = '127.0.0.1:50075';
    private const string DEAD_ADDRESS = '127.0.0.1:50079';
    private const string SOCKET = '/tmp/thesis-grpc-authority-test.sock';

    /**
     * @param non-empty-string $target
     * @param non-empty-string $authority
     */
    #[DataProvider('provideAuthorityCases')]
    public function testAuthority(string $target, string $authority, bool $tls = false): void
    {
        $authorities = [];
        @unlink(self::SOCKET);

        $builder = new Server\Builder()
            ->withAddresses(self::ADDRESS, new UnixAddress(self::SOCKET))
            ->withServices(new EchoServiceServerRegistry(new readonly class implements EchoServiceServer {
                #[\Override]
                public function echo(EchoRequest $request, Metadata $md, Cancellation $cancellation): EchoResponse
                {
                    return new EchoResponse($request->sentence);
                }
            }))
            ->withMiddlewares(new ClosureMiddleware(static function (Request $request, RequestHandler $next) use (&$authorities): Response {
                $authorities[] = $request->getUri()->getAuthority();

                return $next->handleRequest($request);
            }));

        $client = new Client\Builder()->withHost($target);

        if ($tls) {
            $builder = $builder->withTransportCredentials(
                new Server\TransportCredentials()->withDefaultCertificate(new Certificate(self::tls('server.crt'), self::tls('server.key'))),
            );
            $client = $client->withTransportCredentials(
                new Client\TransportCredentials()->withCaCert(self::tls('ca.crt'))->withPeerName('localhost'),
            );
        }

        $server = $builder->build();
        $server->start();

        try {
            new EchoServiceClient($client->build())->echo(new EchoRequest('ping'));
        } finally {
            $server->stop();
        }

        self::assertSame([$authority], $authorities);
    }

    /**
     * @return iterable<string, array{0: non-empty-string, 1: non-empty-string, 2?: bool}>
     */
    public static function provideAuthorityCases(): iterable
    {
        yield 'host name, not the resolved address' => ['localhost:50075', 'localhost:50075'];

        yield 'dns scheme' => ['dns:///localhost:50075', 'localhost:50075'];

        yield 'ipv4' => ['ipv4:' . self::ADDRESS, self::ADDRESS];

        yield 'first of several addresses, whichever the call goes to' => ['ipv4:' . self::DEAD_ADDRESS . ',' . self::ADDRESS, self::DEAD_ADDRESS];

        yield 'unix socket' => ['unix://' . self::SOCKET, 'localhost'];

        // The peer name has no port: the amphp server fills in the port of the connection.
        yield 'peer name of the transport credentials' => ['ipv4:' . self::ADDRESS, 'localhost:50075', true];
    }

    /**
     * @param non-empty-string $filename
     * @return non-empty-string
     */
    private static function tls(string $filename): string
    {
        return __DIR__ . "/Stub/tls/{$filename}";
    }
}
