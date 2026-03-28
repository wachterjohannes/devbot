.PHONY: build-linux build-linux-arm64 build-macos prepare clean test lint phpstan

VERSION ?= $(shell git describe --tags --always --dirty 2>/dev/null || echo "dev")
DIST_DIR = dist

# ──────────────────────────────────────────────
# Development
# ──────────────────────────────────────────────

test:
	php vendor/bin/phpunit

lint:
	php vendor/bin/php-cs-fixer fix

phpstan:
	php vendor/bin/phpstan analyse

# ──────────────────────────────────────────────
# Build preparation
# ──────────────────────────────────────────────

prepare:
	@echo "Preparing app for embedding..."
	./build/prepare.sh

clean:
	rm -rf $(DIST_DIR)

# ──────────────────────────────────────────────
# Linux builds (via Docker)
# ──────────────────────────────────────────────

build-linux: prepare
	@echo "Building Linux x86_64 binary..."
	docker build \
		--build-arg TARGETARCH=amd64 \
		--platform linux/amd64 \
		-f build/Dockerfile \
		-o type=local,dest=$(DIST_DIR)/out \
		.
	mv $(DIST_DIR)/out/devbot $(DIST_DIR)/devbot-linux-x86_64
	rm -rf $(DIST_DIR)/out
	@echo "Built: $(DIST_DIR)/devbot-linux-x86_64"
	@ls -lh $(DIST_DIR)/devbot-linux-x86_64

build-linux-arm64: prepare
	@echo "Building Linux ARM64 binary..."
	docker build \
		--build-arg TARGETARCH=arm64 \
		--platform linux/arm64 \
		-f build/Dockerfile \
		-o type=local,dest=$(DIST_DIR)/out \
		.
	mv $(DIST_DIR)/out/devbot $(DIST_DIR)/devbot-linux-arm64
	rm -rf $(DIST_DIR)/out
	@echo "Built: $(DIST_DIR)/devbot-linux-arm64"
	@ls -lh $(DIST_DIR)/devbot-linux-arm64

# ──────────────────────────────────────────────
# macOS build (native, requires Go + FrankenPHP source)
# ──────────────────────────────────────────────

build-macos: prepare
	@echo "Building macOS binary..."
	@if [ ! -d "$(DIST_DIR)/frankenphp" ]; then \
		echo "Cloning FrankenPHP..."; \
		git clone --depth 1 https://github.com/dunglas/frankenphp.git $(DIST_DIR)/frankenphp; \
	fi
	cd $(DIST_DIR)/frankenphp && \
		EMBED=$(CURDIR)/$(DIST_DIR)/app/ \
		PHP_EXTENSIONS="ctype,curl,dom,fileinfo,filter,iconv,intl,ldap,mbstring,opcache,openssl,pcntl,pdo,pdo_sqlite,posix,readline,session,simplexml,sockets,sqlite3,tokenizer,xml,xmlwriter,zip,zlib" \
		PHP_VERSION="8.4" \
		./build-static.sh
	@ARCH=$$(uname -m); \
	if [ "$$ARCH" = "arm64" ]; then ARCH="aarch64"; fi; \
	cp $(DIST_DIR)/frankenphp/dist/frankenphp-mac-$$ARCH $(DIST_DIR)/devbot-macos-$$(uname -m)
	@echo "Built: $(DIST_DIR)/devbot-macos-$$(uname -m)"
	@ls -lh $(DIST_DIR)/devbot-macos-*

# ──────────────────────────────────────────────
# Build all platforms
# ──────────────────────────────────────────────

build-all: build-linux build-linux-arm64
	@echo "All Linux binaries built in $(DIST_DIR)/"
	@ls -lh $(DIST_DIR)/devbot-*
