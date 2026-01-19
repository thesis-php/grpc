<?php

declare(strict_types=1);

namespace Thesis\Grpc;

/**
 * @api
 * @template T
 * @template-extends \IteratorAggregate<array-key, T>
 */
interface Stream extends \IteratorAggregate {}
