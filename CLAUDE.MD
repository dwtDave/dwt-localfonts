# WordPress Plugin Development Guidelines with Testing

You are an expert in WordPress plugin development, PHP, and related web technologies. These guidelines will help you write clean, modern, and maintainable code.

## Core Principles

**Provide Precise Examples**: Offer clear, technical PHP and WordPress examples that demonstrate best practices for plugin development.

**Adhere to Standards**: Follow PHP and WordPress coding standards for consistency and readability.

**Emphasize OOP**: Use object-oriented programming (OOP) to build modular, scalable, and maintainable plugins. Employ namespacing to prevent conflicts.

**Promote Reusability**: Focus on code reusability through iteration and modular design to avoid duplication (DRY principle).

**Use Clear Naming**: Employ descriptive and meaningful names for functions, variables, classes, and files, prefixed uniquely for your plugin to prevent global namespace collisions.

**Follow Directory Conventions**: Use lowercase with hyphens for directory names (e.g., wp-content/plugins/my-custom-plugin).

**Leverage Hooks**: Extend WordPress functionality using the hook system (actions and filters) instead of modifying core files. This is the foundation of plugin development.

**Document Your Code**: Add clear, descriptive PHPDoc blocks and inline comments to explain complex logic and improve code clarity and maintainability.

## PHP & WordPress Coding Practices

**Utilize Modern PHP**: Leverage features of PHP 8.2+, such as:
- Typed properties and readonly properties.
- Arrow functions (fn() => ...).
- Constructor property promotion.
- The nullsafe operator (?->).
- match expressions.
- Disjunctive Normal Form (DNF) types.

**Enable Strict Typing**: Start every PHP file with declare(strict_types=1); to enforce strict type checking.

**Import Classes**: Always prefer importing classes with the use statement at the top of the file over using fully qualified class names (FQCN) inline.

**Use Core APIs**: Leverage core WordPress functions and APIs (WP_Query, get_post_meta(), Settings API, etc.) whenever possible.

**Implement Robust Error Handling**:
- Use WordPress's built-in debug logging by enabling WP_DEBUG_LOG.
- Apply try-catch blocks for controlled exception handling where operations might fail.
- Use WP_Error objects to return detailed error information from functions.

**Sanitize and Validate Data**: Always use WordPress's built-in functions (e.g., sanitize_text_field(), absint(), wp_kses_post()) for data validation and sanitization on all inputs.

**Secure Form Handling**: Verify nonces (wp_verify_nonce()) in all form submissions and AJAX/REST API requests to prevent Cross-Site Request Forgery (CSRF) attacks.

**Safe Database Interactions**:
- Use the $wpdb global object for all custom database interactions.
- Always use $wpdb->prepare() for dynamic queries to prevent SQL injection vulnerabilities.
- Manage custom database table schema changes with the dbDelta() function, typically executed on a plugin activation hook.

## Dependencies & Environment

**Ensure Compatibility**: Confirm that your plugins are compatible with the latest stable version of WordPress and clearly state the minimum required WordPress and PHP versions (PHP 8.2+).

**Manage Dependencies**: Use Composer for dependency management to handle external libraries effectively. Use a tool like mozart/composer-namespace-prefixer to prevent dependency conflicts with other plugins.

## WordPress Plugin Best Practices

**Plugin Lifecycle Hooks**: Use register_activation_hook() for setup tasks (e.g., creating database tables, setting default options), register_deactivation_hook() for temporary cleanup (e.g., flushing rewrite rules), and an uninstall.php file for complete removal of all plugin data (options, tables, etc.).

**Organize Plugin Code**: Structure your plugin files logically. A common approach is to use separate directories like /includes for classes and functions, /admin for backend-specific code, /public for frontend-specific code, and /assets for CSS, JS, and images.

**Manage Permissions**: Use WordPress's built-in user roles and capabilities (current_user_can()) to control access to your plugin's features and data.

**Implement Caching**: Apply the Transients API (set_transient(), get_transient()) to cache data from expensive queries or remote API calls to optimize performance.

**Handle Background Tasks**: Use WP-Cron (wp_schedule_event()) to implement background processing for long-running or recurring tasks. For more demanding tasks, consider a custom queue processor.

**Internationalize Your Code**: Follow best practices for internationalization (i18n) by using WordPress localization functions (e.g., __(), _e(), esc_html__()) and providing a .pot file to make your plugin translation-ready.

**Enqueue Scripts and Styles**: Manage scripts and styles correctly by using wp_enqueue_script() and wp_enqueue_style(), ensuring they are loaded only on the pages where they are needed.

**Use Custom Post Types**: When your plugin requires a unique content structure, create custom post types, custom taxonomies, and custom meta boxes to extend the WordPress data model.

**Store Configuration Securely**: Use the Options API (add_option(), get_option()) or the Settings API for creating admin pages to store plugin configuration data in the database.

**Provide Functionality**: Expose plugin functionality on the front end through shortcodes (add_shortcode()) or custom Gutenberg blocks.

## Testing Best Practices

**Write Comprehensive Tests**: Develop a robust testing suite that includes unit tests and integration tests to ensure code reliability and prevent regressions.

**Testing Framework Setup**:
- Use PHPUnit 9+ as the primary testing framework.
- Install the WordPress testing library (@wordpress/env or wp-cli scaffold plugin-tests).
- Set up a dedicated test database separate from your development database.
- Use WP_UnitTestCase as the base class for tests that require WordPress functionality.

**Test Organization**:
- Structure tests in a /tests directory mirroring your plugin's file structure.
- Organize tests into /tests/unit and /tests/integration directories.
- Name test files with the suffix Test.php (e.g., MyClassTest.php).
- Use descriptive test method names prefixed with test_ (e.g., test_it_creates_custom_post_type()).

**Unit Testing Principles**:
- Write isolated unit tests that don't depend on WordPress or external services.
- Mock WordPress functions using libraries like Brain\Monkey or WP_Mock.
- Test individual methods and classes in isolation.
- Aim for high code coverage (80%+) on business logic.
- Use data providers for testing multiple input scenarios.

**Integration Testing**:
- Extend WP_UnitTestCase for tests that require WordPress core functionality.
- Test interactions between your plugin components and WordPress APIs.
- Use factory methods (e.g., $this->factory()->post->create()) to create test data.
- Clean up test data in tearDown() or tearDownAfterClass() methods.
- Test hooks, filters, and actions to ensure they fire correctly.

**Test Database Management**:
- Never run tests against a production or live development database.
- Use WordPress test fixtures and factories to create consistent test data.
- Leverage transactions or database rollbacks to ensure test isolation.
- Reset global state between tests using setUp() and tearDown() methods.

**Mocking and Test Doubles**:
- Mock external API calls using libraries like WP_Mock or PHPUnit's built-in mocking.
- Create test doubles (stubs, mocks, fakes) for dependencies to isolate the code under test.
- Mock WordPress functions that have side effects (e.g., wp_mail(), wp_remote_get()).
- Use dependency injection to make classes more testable.

**Testing Best Practices**:
- Follow the AAA pattern: Arrange (setup), Act (execute), Assert (verify).
- Write one assertion per concept, but multiple assertions per test are acceptable.
- Make tests independent – each test should run successfully in isolation.
- Keep tests fast by minimizing database queries and external dependencies.
- Use meaningful assertion messages to aid debugging when tests fail.

**Testing WordPress-Specific Features**:
- Test custom post type and taxonomy registration.
- Verify shortcode output and attribute parsing.
- Test REST API endpoints with WP_REST_Request objects.
- Validate AJAX handlers by testing callback functions directly.
- Test user capabilities and permissions thoroughly.
- Verify nonce validation in form handlers and AJAX requests.
- Test filter and action hook callbacks with do_action() and apply_filters().

**Code Coverage**:
- Generate code coverage reports using PHPUnit with Xdebug or PCOV.
- Aim for 80%+ coverage on critical business logic.
- Don't obsess over 100% coverage – focus on testing important code paths.
- Exclude generated code, vendor directories, and view files from coverage reports.

**Continuous Integration**:
- Set up automated testing with GitHub Actions, GitLab CI, or similar platforms.
- Run tests on multiple PHP versions (8.2, 8.3, 8.4) and WordPress versions.
- Fail builds if tests don't pass or code coverage drops below threshold.
- Run coding standards checks (PHPCS with WordPress rules) alongside tests.

**Testing Commands**:

This plugin uses a dual testing approach:
1. **Unit Tests**: Use Brain\Monkey to mock WordPress functions, run directly on the host machine
2. **Integration Tests**: Use wp-env (Docker) with WP_UnitTestCase for full WordPress integration

**Running Unit Tests** (fast, no WordPress required):
```bash
# Run all unit tests (default)
composer test
composer test:unit

# Run with coverage
composer test:coverage-text
composer test:coverage        # HTML report in coverage/html/

# Run specific test file
vendor/bin/phpunit tests/unit/MyClassTest.php --no-coverage
```

**Running Integration Tests** (requires wp-env):
```bash
# 1. Start wp-env Docker environment (first time or after reset)
npm run wp-env:start

# 2. Run integration tests inside Docker container
composer test:integration
npm run test:php:integration

# Stop wp-env when done
npm run wp-env:stop

# Reset wp-env (destroy and restart)
npm run wp-env:reset
```

**wp-env Configuration**:
- WordPress 6.7 core
- PHP 8.2
- Runs on http://localhost:8888 (development)
- Tests run on http://localhost:8889 (test environment)
- Test environment has separate database for isolation

**Run All Tests**:
```bash
# Run all tests (unit + integration)
composer test:all           # Runs scripts/run-all-tests.sh
composer test:php:all       # Runs scripts/run-php-tests.sh

# Run all JS tests (Vitest for React components)
npm test
npm run test:run
npm run test:coverage
```

**Test Organization**:
- Unit tests extend `PHPUnit\Framework\TestCase` with Brain\Monkey for mocking
- Integration tests extend `WP_UnitTestCase` and run in wp-env Docker container
- Bootstrap file (tests/bootstrap.php) detects test type and loads appropriate environment
- Unit tests are fast and run without WordPress (ideal for TDD and CI)
- Integration tests are slower but test real WordPress interactions

**Common Testing Issues**:
- If integration tests fail with "WP_UnitTestCase not found", run `npm run wp-env:start`
- Unit tests should never require wp-env to be running
- Integration tests must run inside wp-env Docker container via `composer test:integration`
- Never run tests against production or live development databases

**Write Testable Code**:
- Use dependency injection instead of global state or singletons.
- Keep methods small and focused (Single Responsibility Principle).
- Separate business logic from WordPress-specific code where possible.
- Make classes open for extension through interfaces and abstractions.
- Avoid static methods and global functions where testability matters.

## Key Conventions

**Plugin API**: Follow the WordPress Plugin API for creating activation/deactivation hooks, filters, and actions to build modular and scalable extensions.

**Data Security**: Apply WordPress's built-in functions for data sanitization, validation, and escaping (esc_html(), esc_attr(), etc.) to secure all user inputs and outputs.

**Custom Queries**: Use WP_Query for complex post queries. For all other database interactions, including custom tables, use the $wpdb object.

**Authentication**: Rely on WordPress's authentication and authorization mechanisms (cookies, nonces, and capabilities) for secure access control.

**AJAX & REST API**: For asynchronous requests, use the WordPress REST API by creating custom endpoints (register_rest_route()). This is the modern standard over the legacy admin-ajax.php approach.

**Hook System**: The foundation of your plugin must be the hook system (add_action(), add_filter(), do_action(), apply_filters()). Build for extensibility.

**Database Transactions**: For operations involving multiple, dependent database writes, use $wpdb->query('START TRANSACTION;'), $wpdb->query('COMMIT;'), and $wpdb->query('ROLLBACK;') to ensure data integrity.

**Scheduled Tasks**: Schedule automated and recurring tasks using the WP-Cron API for reliable background processing.
```

