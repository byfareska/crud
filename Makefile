.DEFAULT_GOAL := help

# The tools are the dev dependencies of the bundle (composer install first). RUN runs them in a container if wanted,
# e.g. make lint RUN="docker run --rm -v $$PWD:/app -w /app <php image>".
RUN ?=
BUNDLE := $(RUN)

.PHONY: help
help: ## Commands
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n"} /^[a-zA-Z0-9_-]+:.*?##/ { printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } ' $(MAKEFILE_LIST)

ai ?= $(if $(CLAUDECODE),1,0)

ifeq ($(ai),1)
PHPCS_FLAGS := -q --no-colors
DEPTRAC_FLAGS := --no-progress --no-ansi
PHPSTAN_FLAGS := --no-progress --no-ansi
RECTOR_FLAGS := --no-progress-bar --no-diffs --no-ansi
endif

.PHONY: lint
lint: ## Lint the bundle: optional dependencies (deptrac), code style, static analysis. make lint fix=1 fixes what it can first. ai=0 forces output for a human, ai=1 for an agent (detected by itself).
	@# Every tool runs, whatever the one before found; it fails at the end when any of them did, as CI does.
	@failed=""; \
	if [ "$(fix)" = "1" ]; then \
		$(BUNDLE) vendor/bin/rector process $(RECTOR_FLAGS) || true; \
		$(BUNDLE) vendor/bin/phpcbf $(PHPCS_FLAGS) || true; \
	fi; \
	$(BUNDLE) vendor/bin/deptrac $(DEPTRAC_FLAGS) || failed="$$failed deptrac"; \
	$(BUNDLE) vendor/bin/phpcs $(PHPCS_FLAGS) || failed="$$failed phpcs"; \
	$(BUNDLE) vendor/bin/phpstan analyse $(PHPSTAN_FLAGS) || failed="$$failed phpstan"; \
	if [ "$(fix)" != "1" ]; then \
		$(BUNDLE) vendor/bin/rector process --dry-run $(RECTOR_FLAGS) || failed="$$failed rector"; \
	fi; \
	$(BUNDLE) composer validate --strict --no-check-publish || failed="$$failed composer-validate"; \
	$(BUNDLE) composer audit || failed="$$failed composer-audit"; \
	if [ -n "$$failed" ]; then echo "lint failed:$$failed"; exit 1; fi; \
	echo "lint passed"

.PHONY: test
test: ## The unit tests of the bundle
	@$(BUNDLE) vendor/bin/phpunit
