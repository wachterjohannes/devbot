#!/usr/bin/env bash
set -euo pipefail

# Prepare DevBot for FrankenPHP embedding
# Creates a production-ready copy in dist/app/

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
DIST_DIR="${PROJECT_DIR}/dist/app"

echo "==> Preparing DevBot for embedding..."

# Clean previous build
rm -rf "$DIST_DIR"
mkdir -p "$DIST_DIR"

# Export from git (clean, no ignored files)
echo "  Exporting from git..."
cd "$PROJECT_DIR"
git archive HEAD | tar -x -C "$DIST_DIR"

# Copy lib/tui (not in git, local path dependency)
echo "  Copying lib/tui..."
mkdir -p "$DIST_DIR/lib"
cp -R "$PROJECT_DIR/lib/tui" "$DIST_DIR/lib/tui"

# Production environment
echo "  Setting production env..."
cat > "$DIST_DIR/.env.local" <<'EOF'
APP_ENV=prod
APP_DEBUG=0
EOF

# Remove dev-only files
echo "  Stripping dev files..."
rm -rf "$DIST_DIR/tests"
rm -rf "$DIST_DIR/build"
rm -rf "$DIST_DIR/.github"
rm -rf "$DIST_DIR/.php-cs-fixer.dist.php"
rm -f "$DIST_DIR/phpstan.neon"
rm -f "$DIST_DIR/phpunit.xml.dist"

# Install production dependencies
echo "  Installing production dependencies..."
cd "$DIST_DIR"
composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --classmap-authoritative \
    --ignore-platform-reqs

# Dump optimized env
composer dump-env prod 2>/dev/null || true

# Create required directories
mkdir -p var/log
mkdir -p memory/{long-term,episodic,short-term,semantic}
mkdir -p kanban
mkdir -p heartbeat
mkdir -p skills

# Copy embedded php.ini
if [ -f "$PROJECT_DIR/build/php.ini" ]; then
    cp "$PROJECT_DIR/build/php.ini" "$DIST_DIR/php.ini"
fi

echo "==> Prepared in $DIST_DIR"
echo "    Size: $(du -sh "$DIST_DIR" | cut -f1)"
