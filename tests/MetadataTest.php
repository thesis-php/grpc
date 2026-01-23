<?php

declare(strict_types=1);

namespace Thesis\Grpc;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Metadata::class)]
final class MetadataTest extends TestCase
{
    public function testMetadata(): void
    {
        $md = new Metadata(['x' => ['y', 'w'], 'A' => 'b']);
        self::assertCount(2, $md);
        self::assertTrue(isset($md['x']));
        self::assertTrue(isset($md['a']));
        self::assertSame(['y', 'w'], $md['x']);
        self::assertSame(['b'], $md['a']);

        $md = $md->without('x');
        self::assertCount(1, $md);
        self::assertFalse(isset($md['x']));

        $md = $md->with('x', 'y', 'w');
        self::assertCount(2, $md);
        self::assertTrue(isset($md['x']));

        $md = $md->with('X', 'z');
        self::assertSame(['y', 'w', 'z'], $md['x']);

        $md = $md->with('a', 'c');
        self::assertSame(['b', 'c'], $md['a']);

        $md = $md->merge(new Metadata(['x' => 'h']));
        self::assertCount(2, $md);
        self::assertSame(['y', 'w', 'z', 'h'], $md['x']);

        $md = $md->replace('x', 'c');
        self::assertSame(['c'], $md['x']);
    }

    public function testCompression(): void
    {
        $md = new Metadata();
        self::assertNull($md->compression());
        self::assertSame('gzip', $md->compression('gzip'));

        $md = $md->with('grpc-encoding', 'deflate');
        self::assertSame('deflate', $md->compression('gzip'));
    }

    public function testEncoding(): void
    {
        $md = new Metadata();
        self::assertNull($md->encoding());
        self::assertSame('proto', $md->encoding('proto'));

        $md = $md->with('content-type', 'application/grpc');
        self::assertSame('avro', $md->encoding('avro'));

        $md = $md->replace('content-type', 'application/grpc+proto');
        self::assertSame('proto', $md->encoding('avro'));
    }
}
