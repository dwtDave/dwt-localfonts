<?php

declare(strict_types=1);

namespace DWT\LocalFonts\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use DWT\LocalFonts\Admin\UpdateSettingsPage;
use DWT\LocalFonts\ValueObjects\UpdateConfiguration;
use PHPUnit\Framework\TestCase;
use Mockery;

/**
 * Test UpdateSettingsPage class
 *
 * Tests WordPress Settings API integration, form rendering, and settings persistence
 */
final class UpdateSettingsPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        // Mock common WordPress functions
        Functions\when('sanitize_key')->returnArg();
        Functions\when('sanitize_text_field')->alias(function($str) {
            return trim(strip_tags($str));
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function test_it_registers_admin_menu(): void
    {
        // Arrange
        $settingsPage = new UpdateSettingsPage();

        Functions\expect('add_options_page')
            ->once()
            ->with(
                'GitHub Updates', // page_title
                'GitHub Updates', // menu_title
                'manage_options', // capability
                'dwt-localfonts-github-updates', // menu_slug
                Mockery::type('callable') // callback
            )
            ->andReturn('settings_page_dwt-localfonts-github-updates');

        // Act
        $settingsPage->registerMenu();

        // Assert - Mockery verifies expectations automatically
        $this->assertTrue(true);
    }

    public function test_it_registers_settings_with_wordpress(): void
    {
        // Arrange
        $settingsPage = new UpdateSettingsPage();

        Functions\expect('register_setting')
            ->once()
            ->with(
                'dwt_localfonts_github_updates', // option_group
                'dwt_localfonts_github_config', // option_name
                Mockery::type('array') // args (includes sanitize_callback)
            );

        Functions\expect('add_settings_section')
            ->once()
            ->with(
                'dwt_localfonts_github_repository', // id
                'GitHub Repository Configuration', // title
                Mockery::type('callable'), // callback
                'dwt-localfonts-github-updates' // page
            );

        Functions\expect('add_settings_field')
            ->times(5); // 5 fields: owner, name, channel, auto-update, cache lifetime

        // Act
        $settingsPage->registerSettings();

        // Assert - Mockery verifies expectations
        $this->assertTrue(true);
    }

    public function test_it_sanitizes_repository_owner(): void
    {
        // Arrange
        $settingsPage = new UpdateSettingsPage();
        $input = [
            'repository_owner' => '  Test-Owner  ',
            'repository_name' => 'test-repo',
            'plugin_slug' => 'dwt-localfonts',
            'update_channel' => 'stable',
            'auto_update_enabled' => false,
            'cache_lifetime' => 43200,
        ];

        Functions\expect('add_settings_error')
            ->never();

        // Act
        $result = $settingsPage->sanitizeSettings($input);

        // Assert - sanitize_text_field() calls trim() internally
        $this->assertSame('Test-Owner', $result['repository_owner']);
        $this->assertSame('test-repo', $result['repository_name']);
    }

    public function test_it_validates_required_fields(): void
    {
        // Arrange
        $settingsPage = new UpdateSettingsPage();
        $input = [
            'repository_owner' => '', // Empty - should fail
            'repository_name' => '', // Empty - should fail
            'plugin_slug' => 'dwt-localfonts',
            'update_channel' => 'stable',
            'auto_update_enabled' => false,
            'cache_lifetime' => 43200,
        ];

        Functions\expect('add_settings_error')
            ->once()
            ->with(
                'dwt_localfonts_github_config',
                'missing_repository_owner',
                'Repository owner is required.',
                'error'
            );

        Functions\expect('add_settings_error')
            ->once()
            ->with(
                'dwt_localfonts_github_config',
                'missing_repository_name',
                'Repository name is required.',
                'error'
            );

        Functions\expect('get_option')
            ->once()
            ->with('dwt_localfonts_github_config', Mockery::type('array'))
            ->andReturn([
                'repository_owner' => 'default-owner',
                'repository_name' => 'default-repo',
                'plugin_slug' => 'dwt-localfonts',
                'update_channel' => 'stable',
                'auto_update_enabled' => false,
                'cache_lifetime' => 43200,
            ]);

        // Act - should return previous valid settings when validation fails
        $result = $settingsPage->sanitizeSettings($input);

        // Assert - returns previous settings when validation fails
        $this->assertSame('default-owner', $result['repository_owner']);
        $this->assertSame('default-repo', $result['repository_name']);
    }

    public function test_it_validates_update_channel_values(): void
    {
        // Arrange
        $settingsPage = new UpdateSettingsPage();
        $input = [
            'repository_owner' => 'test-owner',
            'repository_name' => 'test-repo',
            'plugin_slug' => 'dwt-localfonts',
            'update_channel' => 'invalid-channel', // Invalid value
            'auto_update_enabled' => false,
            'cache_lifetime' => 43200,
        ];

        Functions\expect('add_settings_error')
            ->once()
            ->with(
                'dwt_localfonts_github_config',
                'invalid_update_channel',
                'Update channel must be either "stable" or "all".',
                'error'
            );

        Functions\expect('get_option')
            ->once()
            ->andReturn([
                'repository_owner' => 'test-owner',
                'repository_name' => 'test-repo',
                'plugin_slug' => 'dwt-localfonts',
                'update_channel' => 'stable',
                'auto_update_enabled' => false,
                'cache_lifetime' => 43200,
            ]);

        // Act
        $result = $settingsPage->sanitizeSettings($input);

        // Assert - should use previous valid settings
        $this->assertSame('stable', $result['update_channel']);
    }

    public function test_it_validates_cache_lifetime_range(): void
    {
        // Arrange
        $settingsPage = new UpdateSettingsPage();
        $input = [
            'repository_owner' => 'test-owner',
            'repository_name' => 'test-repo',
            'plugin_slug' => 'dwt-localfonts',
            'update_channel' => 'stable',
            'auto_update_enabled' => false,
            'cache_lifetime' => 100, // Too short (min 3600 seconds)
        ];

        Functions\expect('add_settings_error')
            ->once()
            ->with(
                'dwt_localfonts_github_config',
                'invalid_cache_lifetime',
                'Cache lifetime must be between 3600 and 86400 seconds (1-24 hours).',
                'error'
            );

        Functions\expect('get_option')
            ->once()
            ->andReturn([
                'repository_owner' => 'test-owner',
                'repository_name' => 'test-repo',
                'plugin_slug' => 'dwt-localfonts',
                'update_channel' => 'stable',
                'auto_update_enabled' => false,
                'cache_lifetime' => 43200,
            ]);

        // Act
        $result = $settingsPage->sanitizeSettings($input);

        // Assert - should use previous valid cache lifetime
        $this->assertSame(43200, $result['cache_lifetime']);
    }

    public function test_it_converts_settings_to_update_configuration(): void
    {
        // Arrange
        $settingsPage = new UpdateSettingsPage();
        $settings = [
            'repository_owner' => 'test-owner',
            'repository_name' => 'test-repo',
            'plugin_slug' => 'dwt-localfonts',
            'update_channel' => 'all',
            'auto_update_enabled' => true,
            'cache_lifetime' => 21600,
        ];

        Functions\expect('get_option')
            ->once()
            ->with('dwt_localfonts_github_config', Mockery::type('array'))
            ->andReturn($settings);

        // Act
        $config = $settingsPage->getConfiguration();

        // Assert
        $this->assertInstanceOf(UpdateConfiguration::class, $config);
        $this->assertSame('test-owner', $config->repositoryOwner);
        $this->assertSame('test-repo', $config->repositoryName);
        $this->assertSame('dwt-localfonts', $config->pluginSlug);
        $this->assertSame('all', $config->updateChannel);
        $this->assertTrue($config->autoUpdateEnabled);
        $this->assertSame(21600, $config->cacheLifetime);
    }

    public function test_it_provides_default_configuration_when_no_settings_exist(): void
    {
        // Arrange
        $settingsPage = new UpdateSettingsPage();

        Functions\expect('get_option')
            ->once()
            ->with('dwt_localfonts_github_config', Mockery::type('array'))
            ->andReturn([]);

        // Act & Assert - should throw exception when empty settings (invalid configuration)
        // Empty repository owner/name are invalid and should fail validation
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid repository owner format');

        $settingsPage->getConfiguration();
    }

    public function test_it_checks_user_capability(): void
    {
        // Arrange
        $settingsPage = new UpdateSettingsPage();

        Functions\expect('current_user_can')
            ->once()
            ->with('manage_options')
            ->andReturn(false);

        Functions\when('esc_html__')->alias(fn($text) => $text);
        Functions\when('settings_errors')->justReturn();
        Functions\when('settings_fields')->justReturn();
        Functions\when('do_settings_sections')->justReturn();
        Functions\when('submit_button')->justReturn();

        Functions\expect('wp_die')
            ->once()
            ->with('You do not have sufficient permissions to access this page.');

        // Act - capture any output that might be generated before wp_die
        ob_start();
        $settingsPage->renderPage();
        ob_end_clean();

        // Assert - wp_die called, test completes
        $this->assertTrue(true);
    }

    public function test_it_renders_settings_page_for_authorized_users(): void
    {
        // Arrange
        $settingsPage = new UpdateSettingsPage();

        Functions\expect('current_user_can')
            ->once()
            ->with('manage_options')
            ->andReturn(true);

        Functions\when('esc_html__')->alias(fn($text) => $text);
        Functions\when('esc_attr')->alias(fn($text) => $text);
        Functions\when('esc_html')->alias(fn($text) => $text);

        Functions\expect('settings_errors')
            ->once()
            ->with('dwt_localfonts_github_config');

        Functions\expect('settings_fields')
            ->once()
            ->with('dwt_localfonts_github_updates');

        Functions\expect('do_settings_sections')
            ->once()
            ->with('dwt-localfonts-github-updates');

        Functions\expect('submit_button')
            ->once();

        // Act
        ob_start();
        $settingsPage->renderPage();
        $output = ob_get_clean();

        // Assert - should render page without wp_die
        $this->assertIsString($output);
        $this->assertStringContainsString('GitHub Updates Settings', $output);
    }
}
