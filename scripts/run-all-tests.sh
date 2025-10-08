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
# Check if wp-env is running
if ! npx wp-env status > /dev/null 2>&1; then
    echo "⚠ wp-env is not running. Starting wp-env..."
    npx wp-env start
    if [ $? -ne 0 ]; then
        echo "✗ Failed to start wp-env"
        INTEGRATION_EXIT=1
    else
        npx wp-env run tests-cli --env-cwd=wp-content/plugins/dwt-localfonts vendor/bin/phpunit -c phpunit-integration.xml.dist
        INTEGRATION_EXIT=$?
    fi
else
    npx wp-env run tests-cli --env-cwd=wp-content/plugins/dwt-localfonts vendor/bin/phpunit -c phpunit-integration.xml.dist
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
