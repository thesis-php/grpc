<?php

declare(strict_types=1);

namespace Thesis\Grpc;

use Amp\Cancellation;
use Amp\Future;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\Request as HttpRequest;
use Amp\Http\Client\Response as HttpResponse;
use Amp\NullCancellation;
use Amp\Pipeline;
use Thesis\Grpc\Client\Internal\Http2;
use Thesis\Grpc\Client\Internal\Transport;
use Thesis\Grpc\Exception\ClientStreamIsClosed;
use Thesis\Grpc\Internal\Http2\Stream;
use Thesis\Package;
use function Amp\async;

/**
 * @api
 */
final readonly class Client
{
    private Http2\GrpcStream $stream;

    public function __construct(
        private DelegateHttpClient $transport,
        private Transport\UriFactory $uri,
        private Encoding\Encoder $encoder,
        private Compression\Compressor $compressor,
    ) {
        $this->stream = new Http2\GrpcStream(
            $encoder,
            $compressor,
        );
    }

    /**
     * @template E of object
     * @param non-empty-string $method
     * @param class-string<E> $replyType
     * @return E
     * @throws ClientStreamIsClosed
     */
    public function invoke(
        string $method,
        object $request,
        string $replyType,
        Metadata $md = new Metadata(),
        Cancellation $cancellation = new NullCancellation(),
    ): object {
        $stream = $this->call(
            new Request($method, $replyType, $md),
            $cancellation,
        );

        $stream->send($request);
        $stream->close();

        return $stream->receive();
    }

    /**
     * @template In of object
     * @template Out of object
     * @param Request<In, Out> $request
     * @return ClientStream<In, Out>
     */
    public function call(Request $request, Cancellation $cancellation = new NullCancellation()): ClientStream
    {
        /** @var ?non-empty-string $version */
        static $version;
        $version ??= Package\version('thesis/grpc');

        $md = new Metadata([
            'Content-Type' => "application/grpc+{$this->encoder->name()}",
            'User-Agent' => "grpc-php-thesis/{$version}",
            'grpc-encoding' => $this->compressor->name(),
            'TE' => 'trailers',
        ]);

        /** @var Pipeline\Queue<In> $send */
        $send = new Pipeline\Queue();

        $req = new HttpRequest(
            uri: $this->uri->create($request->method),
            method: 'POST',
            body: $this->stream->encode($send->iterate()),
        );
        $req->setProtocolVersions(['2']);
        $req->setHeaders($md->merge($request->md)->kv);

        /** @var Future<HttpResponse> $response */
        $response = async($this->transport->request(...), $req, $cancellation);

        return new Stream\BoundedClientStream(
            $response,
            $send,
            fn(HttpResponse $response) => $this->stream->decode($response->getBody(), $request->replyType),
        );
    }
}
