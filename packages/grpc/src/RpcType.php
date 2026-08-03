<?php

declare(strict_types=1);

namespace Thesis\Grpc;

/**
 * @api
 */
enum RpcType
{
    case Unary;
    case ClientStream;
    case ServerStream;
    case BidirectionalStream;
}
