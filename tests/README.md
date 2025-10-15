# DWT Management for WP - Tests

This directory contains the test suite for the DWT Management for WP plugin.

## Test Structure

```
tests/
├── bootstrap.php           # PHPUnit bootstrap file
├── unit/                   # Unit tests
│   ├── CoreTest.php
│   ├── FontManagerTest.php
│   ├── PerformanceTest.php
│   ├── SecurityTest.php
│   └── SettingsTest.php
└── integration/            # Integration tests (for future use)
```

## Running Tests

### Run All Tests

```bash
composer test
# or
vendor/bin/phpunit
```

### Run Only Unit Tests

```bash
vendor/bin/phpunit --testsuite="Unit Tests"
```

### Run Specific Test File

```bash
vendor/bin/phpunit tests/unit/CoreTest.php
```

### Run with Test Documentation

```bash
vendor/bin/phpunit --testdox
```

### Run with Code Coverage

```bash
vendor/bin/phpunit --coverage-html coverage/
```

Then open `coverage/index.html` in your browser to view the coverage report.

## Test Coverage

Current test coverage includes:

- **Core**: Singleton pattern, module initialization, textdomain loading
- **FontManager**: Google Fonts management, REST API endpoints, CSS generation
- **Settings**: REST API for settings, data sanitization, React app integration
- **Security**: Security hardening features (XML-RPC, file editor)
- **Performance**: Performance optimization features (emojis, embeds, header cleanup)

## Testing Tools

- **PHPUnit 9.6**: Main testing framework
- **Brain Monkey 2.6**: WordPress function mocking
- **Mockery 1.6**: General purpose mocking library

## Writing Tests

### Basic Test Structure

```php
<?php

declare(strict_types=1);

namespace DWT\CoreTweaks\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

final class ExampleTest extends TestCase {
    use MockeryPHPUnitIntegration;

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        // Mock WordPress functions
        Functions\when('add_action')->justReturn(true);
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_it_does_something(): void {
        // Arrange
        $instance = new YourClass();

        // Act
        $result = $instance->doSomething();

        // Assert
        $this->assertTrue($result);
    }
}
```

### Mocking WordPress Functions

```php
// Simple mock that returns true
Functions\when('wp_verify_nonce')->justReturn(true);

// Mock with specific return value
Functions\when('get_option')->justReturn(['key' => 'value']);

// Mock that expects to be called
Functions\expect('update_option')
    ->once()
    ->with('option_name', ['data'])
    ->andReturn(true);
```

## Continuous Integration

Tests should be run automatically in CI/CD pipelines. Example GitHub Actions configuration:

```yaml
- name: Run Tests
  run: composer test
```

## Future Improvements

- [ ] Add integration tests for REST API endpoints
- [ ] Add tests for admin UI components
- [ ] Implement E2E tests using WordPress testing framework
- [ ] Increase code coverage to 90%+
- [ ] Add mutation testing with Infection