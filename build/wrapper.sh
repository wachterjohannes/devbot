#!/bin/sh
DIR="$(cd "$(dirname "$0")" && pwd)"
exec "$DIR/devbot-bin" php-cli bin/devbot "$@"
