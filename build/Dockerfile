# Build static DevBot binary for Linux
# Usage:
#   docker build -t devbot-builder -f build/Dockerfile .
#   docker cp $(docker create devbot-builder):/go/src/app/dist/frankenphp-linux-$(uname -m) devbot

ARG TARGETARCH=amd64

FROM --platform=linux/${TARGETARCH} dunglas/frankenphp:static-builder-gnu AS builder

WORKDIR /go/src/app/dist/app

# Copy pre-prepared app (run build/prepare.sh first)
COPY dist/app/ .

WORKDIR /go/src/app/

# Build with default extensions (auto-resolves all transitive dependencies)
RUN EMBED=dist/app/ \
    PHP_VERSION="8.4" \
    ./build-static.sh

FROM scratch AS binary
COPY --from=builder /go/src/app/dist/frankenphp-linux-* /devbot
