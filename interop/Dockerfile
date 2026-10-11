ARG PHP_IMAGE_VERSION

FROM golang:1.25-alpine AS grpc-go
ARG GRPC_GO_VERSION=v1.84.0
RUN CGO_ENABLED=0 go install \
    google.golang.org/grpc/interop/client@${GRPC_GO_VERSION} \
    google.golang.org/grpc/interop/server@${GRPC_GO_VERSION}

FROM ghcr.io/phpyh/php:${PHP_IMAGE_VERSION}
COPY --from=grpc-go /go/bin/client /usr/local/bin/grpc-go-interop-client
COPY --from=grpc-go /go/bin/server /usr/local/bin/grpc-go-interop-server
