#!/bin/bash

# Run all tests and collect results
# This script runs unit, integration, and JS tests regardless of individual failures

set +e  # Don't exit on error

echo "=================================="
echo "Running All Tests"
echo "=================================="
echo ""

# Run unit tests
echo "→ Running Unit Tests..."
XDEBUG_MODE=off vendor/bin/phpunit --configuration phpunit-unit.xml.dist --no-coverage
UNIT_EXIT=$?
echo ""

# Run integration tests
echo "→ Running Integration Tests..."
WP_ENV_CMD="npx -y wp-env"

# Check if wp-env is running; if not, start it non-interactively
if ! ${WP_ENV_CMD} status > /dev/null 2>&1; then
    echo "⚠ wp-env is not running. Starting wp-env..."
    ${WP_ENV_CMD} start --update
    if [ $? -ne 0 ]; then
        echo "✗ Failed to start wp-env"
        INTEGRATION_EXIT=1
    else
        # Wait for the tests environment to be truly ready (WP CLI responsive)
        echo "⏳ Waiting for wp-env tests CLI to become ready..."
        waited=0
        max_wait=120
        until ${WP_ENV_CMD} run tests-cli wp core is-installed > /dev/null 2>&1 || [ $waited -ge $max_wait ]; do
            sleep 2
            waited=$((waited + 2))
        done
        if ! ${WP_ENV_CMD} run tests-cli wp core is-installed > /dev/null 2>&1; then
            echo "✗ wp-env tests CLI did not become ready within ${max_wait}s"
            INTEGRATION_EXIT=1
        else
            ${WP_ENV_CMD} run tests-cli --env-cwd=wp-content/plugins/dwt-localfonts vendor/bin/phpunit -c phpunit-integration.xml.dist
            INTEGRATION_EXIT=$?
        fi
    fi
else
    # Ensure tests CLI is responsive even if wp-env reports running
    echo "⏳ Verifying wp-env tests CLI readiness..."
    waited=0
    max_wait=60
    until ${WP_ENV_CMD} run tests-cli wp core is-installed > /dev/null 2>&1 || [ $waited -ge $max_wait ]; do
        sleep 2
        waited=$((waited + 2))
    done
    if ! ${WP_ENV_CMD} run tests-cli wp core is-installed > /dev/null 2>&1; then
        echo "✗ wp-env tests CLI is not ready; skipping integration tests"
        INTEGRATION_EXIT=1
    else
        ${WP_ENV_CMD} run tests-cli --env-cwd=wp-content/plugins/dwt-localfonts vendor/bin/phpunit -c phpunit-integration.xml.dist
    fi
    INTEGRATION_EXIT=$?
fi
echo ""

# Run JS tests
echo "→ Running JavaScript Tests..."
npm run test:run
JS_EXIT=$?
echo ""

# Summary
echo "=================================="
echo "Test Summary"
echo "=================================="
echo "Unit Tests:        $([ $UNIT_EXIT -eq 0 ] && echo '✓ PASS' || echo '✗ FAIL')"
echo "Integration Tests: $([ $INTEGRATION_EXIT -eq 0 ] && echo '✓ PASS' || echo '✗ FAIL')"
echo "JavaScript Tests:  $([ $JS_EXIT -eq 0 ] && echo '✓ PASS' || echo '✗ FAIL')"
echo "=================================="

# Exit with error if any test suite failed
if [ $UNIT_EXIT -ne 0 ] || [ $INTEGRATION_EXIT -ne 0 ] || [ $JS_EXIT -ne 0 ]; then
    exit 1
fi

exit 0
