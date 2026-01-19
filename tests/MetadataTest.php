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
    }
}
