<?php

declare(strict_types=1);

namespace Thesis\Grpc\Internal\Http2;

use Amp\ByteStream\ReadableIterableStream;
use Amp\Cancellation;
use Amp\Future;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\HttpContent;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Client\StreamedContent;
use Amp\NullCancellation;
use Amp\Pipeline;
use Revolt\EventLoop;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Compression\Compressor;
use Thesis\Grpc\Encoding\Encoder;
use Thesis\Grpc\Internal\Protocol;
use Thesis\Grpc\Invoke;
use Thesis\Grpc\Metadata;
use function Amp\async;

/**
 * @internal
 */
final readonly class Transport
{
    public function __construct(
        private DelegateHttpClient $http,
        private UriFactory $uri,
        private Encoder $encoder,
        private Compressor $compressor,
    ) {}

    /**
     * @template In of object
     * @template Out of object
     * @param Invoke<In, Out> $invoke
     * @return ClientStream<In, Out>
     */
    public function createClientStream(
        Invoke $invoke,
        Metadata $md = new Metadata(),
        Cancellation $cancellation = new NullCancellation(),
    ): ClientStream {
        /** @var Pipeline\Queue<In> $send */
        $send = new Pipeline\Queue();

        $req = new Request(
            uri: $this->uri->create($invoke->method),
            method: 'POST',
            body: $this->encodeStream($send->iterate()),
        );
        $req->setProtocolVersions(['2']);
        $req->setHeaders($md->kv);

        /** @var Future<Response> $response */
        $response = async($this->http->request(...), $req, $cancellation);

        return new Stream\ConcurrentClientStream(
            $response,
            $send,
            fn(Response $response) => $this->decodeStream($response->getBody(), $invoke->type),
        );
    }

    /**
     * @template T of object
     * @param Pipeline\ConcurrentIterator<T> $in
     */
    private function encodeStream(Pipeline\ConcurrentIterator $in): HttpContent
    {
        /** @var Pipeline\Queue<non-empty-string> $out */
        $out = new Pipeline\Queue();

        $encoder = $this->encoder;
        $compressor = $this->compressor;

        EventLoop::queue(static function () use (
            $encoder,
            $compressor,
            $in,
            $out,
        ): void {
            foreach ($in as $message) {
                $buffer = $compressed = $encoder->encode($message);

                if ($buffer !== '') {
                    $compressed = $compressor->compress($buffer);
                }

                $frame = Protocol\encodeFrame(new Protocol\Frame(
                    $compressed !== $buffer,
                    $compressed,
                ));

                $out->push($frame);
            }

            $out->complete();
        });

        return StreamedContent::fromStream(
            new ReadableIterableStream($out->iterate()),
        );
    }

    /**
     * @template T of object
     * @param \Traversable<string> $in
     * @param class-string<T> $classType
     * @return Pipeline\ConcurrentIterator<T>
     */
    public function decodeStream(\Traversable $in, string $classType): Pipeline\ConcurrentIterator
    {
        /** @var Pipeline\Queue<T> $out */
        $out = new Pipeline\Queue();

        $encoder = $this->encoder;
        $compressor = $this->compressor;

        EventLoop::queue(static function () use (
            $encoder,
            $compressor,
            $in,
            $out,
            $classType,
        ): void {
            /** @var string $message */
            foreach ($in as $message) {
                if ($message === '') {
                    continue;
                }

                $frame = Protocol\decodeFrame($message);

                $buffer = $frame->buffer;

                if ($frame->compressed && $buffer !== '') {
                    $buffer = $compressor->decompress($buffer);
                }

                $out->push($encoder->decode($buffer, $classType));
            }

            $out->complete();
        });

        return $out->iterate();
    }
}
