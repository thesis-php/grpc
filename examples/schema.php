<?php

declare(strict_types=1);

use Thesis\Protobuf\Reflection;

/**
 * @api
 */
final readonly class EchoRequest
{
    public function __construct(
        #[Reflection\Field(1, Reflection\StringT::T)]
        public string $word,
    ) {}
}

/**
 * @api
 */
final readonly class EchoResponse
{
    public function __construct(
        #[Reflection\Field(1, Reflection\StringT::T)]
        public string $word,
    ) {}
}
