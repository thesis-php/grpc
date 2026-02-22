<?php

declare(strict_types=1);

namespace Thesis\Grpc\Status;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Thesis\Grpc\Metadata;
use Google\Rpc;
use Thesis\Protobuf\Encoder;
use Thesis\Protobuf\Decoder;

#[CoversClass(Context::class)]
#[CoversFunction('Thesis\Grpc\Status\serializeContext')]
#[CoversFunction('Thesis\Grpc\Status\deserializeContext')]
final class ContextTest extends TestCase
{
    #[TestWith([
        new Context(Rpc\Code::OK),
    ])]
    #[TestWith([
        new Context(Rpc\Code::UNAUTHENTICATED, 'Use authentication, Luke'),
    ])]
    #[TestWith([
        new Context(Rpc\Code::INVALID_ARGUMENT, 'Bad request', [
            new Rpc\BadRequest([
                new Rpc\BadRequest\FieldViolation('name', 'Must not be empty'),
            ]),
        ]),
    ])]
    public function testContext(Context $context): void
    {
        $status = serializeContext($context, Encoder\Builder::buildDefault());
        self::assertSame($context->code, $status->code);
        self::assertSame($context->message, $status->message);

        if ($context->details !== []) {
            self::assertNotNull($status->details);
        }

        $md = new Metadata()->withKey($status);

        $de = deserializeContext($md, Decoder\Builder::buildDefault());
        self::assertEquals($context, $de);
    }
}
