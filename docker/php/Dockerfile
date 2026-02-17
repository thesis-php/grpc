FROM alpine:3.20 AS protoc

ARG PROTOC_VERSION=33.5
ARG PROTOC_PLUGIN_VERSION=0.1.5

RUN apk add --no-cache curl unzip && \
    curl -LO "https://github.com/protocolbuffers/protobuf/releases/download/v${PROTOC_VERSION}/protoc-${PROTOC_VERSION}-linux-x86_64.zip" && \
    unzip "protoc-${PROTOC_VERSION}-linux-x86_64.zip" -d /usr/local && \
    rm "protoc-${PROTOC_VERSION}-linux-x86_64.zip"

RUN curl -L https://github.com/thesis-php/protoc-plugin/releases/download/${PROTOC_PLUGIN_VERSION}/protoc-gen-php \
    -o /usr/local/bin/protoc-gen-php \
    && chmod +x /usr/local/bin/protoc-gen-php

FROM ghcr.io/phpyh/php:8.4

COPY --from=protoc /usr/local/bin/protoc /usr/local/bin/protoc
COPY --from=protoc /usr/local/include /usr/local/include
COPY --from=protoc /usr/local/bin/protoc-gen-php /usr/local/bin/protoc-gen-php
