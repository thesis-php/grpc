# thesis/grpc-protocol

> Read-only subtree split from `https://github.com/thesis-php/grpc`.
>
> Do not open issues/PRs here. Use the monorepo:
> - https://github.com/thesis-php/grpc/issues
> - https://github.com/thesis-php/grpc/pulls

Shared gRPC protocol runtime for PHP: framing, metadata, status/error model, and encoding/compression primitives used by client and server.

## Contents

- [Installation](#installation)
- [Usage](#usage)

## Installation

```bash
composer require thesis/grpc-protocol
```

## Usage

This package is typically consumed transitively by:

- `thesis/grpc-client`
- `thesis/grpc-server`

You can also require it directly for shared runtime primitives:

- metadata and status context
- gRPC frame codec
- compression and encoding contracts
