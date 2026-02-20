<?php

declare(strict_types=1);

namespace Thesis\Grpc\Metadata;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Thesis\Grpc\Metadata;

#[CoversClass(ContentType::class)]
#[CoversFunction('Thesis\Grpc\Metadata\parseContentType')]
final class ContentTypeTest extends TestCase
{
    #[TestWith([
        'application/grpc',
        new ContentType('proto'),
    ])]
    #[TestWith([
        'application/grpc;',
        new ContentType('proto'),
    ])]
    #[TestWith([
        'application/grpc+proto',
        new ContentType('proto'),
    ])]
    #[TestWith([
        'application/grpc+avro',
        new ContentType('avro'),
    ])]
    #[TestWith([
        'application/grpc-json',
        null,
    ])]
    #[TestWith([
        '',
        null,
    ])]
    public function testContentTypeParsed(string $spec, ?ContentType $contentType = null): void
    {
        self::assertEquals($contentType, parseContentType(new Metadata()->with(ContentType::HEADER, $spec)));
    }

    /**
     * @param non-empty-string $header
     */
    #[TestWith([
        new ContentType(),
        'application/grpc',
    ])]
    #[TestWith([
        new ContentType('proto'),
        'application/grpc+proto',
    ])]
    public function testContentTypeInMetadata(ContentType $contentType, string $header): void
    {
        $md = new Metadata();
        $md = $md->withKey($contentType);

        self::assertSame($header, $md->value(ContentType::HEADER));
    }
}
