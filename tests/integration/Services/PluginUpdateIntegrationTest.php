<?php

declare(strict_types=1);

namespace DWT\LocalFonts\Tests\Integration\Services;

use DWT\LocalFonts\Services\PluginUpdateIntegration;
use DWT\LocalFonts\Services\GitHubUpdateService;
use DWT\LocalFonts\Services\AssetResolver;
use DWT\LocalFonts\Services\UpdateLogger;
use DWT\LocalFonts\ValueObjects\UpdateConfiguration;
use DWT\LocalFonts\ValueObjects\GitHubRelease;
use DWT\LocalFonts\Tests\Integration\HttpMockTrait;
use WP_UnitTestCase;

/**
 * Integration test for PluginUpdateIntegration
 *
 * Tests WordPress transient manipulation and filter integration
 */
final class PluginUpdateIntegrationTest extends WP_UnitTestCase
{
    use HttpMockTrait;

    private UpdateConfiguration $config;
    private string $pluginBasename;

    public function setUp(): void
    {
        parent::setUp();

        // Mock HTTP requests to prevent 404 errors in test output
        $this->mockHttpRequests();

        // Clean up transients before each test
        delete_transient('dwt_localfonts_github_release');
        delete_site_transient('update_plugins');

        $this->config = new UpdateConfiguration(
            repositoryOwner: 'test-owner',
            repositoryName: 'test-repo',
            pluginSlug: 'dwt-localfonts',
            cacheLifetime: 43200,
            updateChannel: 'stable',
            autoUpdateEnabled: false
        );

        $this->pluginBasename = 'dwt-localfonts/dwt-localfonts.php';
    }

    public function tearDown(): void
    {
        // Remove HTTP mocks
        $this->removeHttpMocks();

        // Clean up transients after each test
        delete_transient('dwt_localfonts_github_release');
        delete_site_transient('update_plugins');

        parent::tearDown();
    }

    public function test_it_registers_wordpress_hooks(): void
    {
        // Arrange
        $assetResolver = new AssetResolver();
        $logger = new UpdateLogger();
        $updateService = new GitHubUpdateService($this->config, $assetResolver, $logger);
        $integration = new PluginUpdateIntegration($updateService, $this->pluginBasename);

        // Act
        $integration->registerHooks();

        // Assert - Verify filters are registered
        $this->assertNotFalse(
            has_filter('pre_set_site_transient_update_plugins'),
            'pre_set_site_transient_update_plugins filter should be registered'
        );

        $this->assertNotFalse(
            has_filter('plugins_api'),
            'plugins_api filter should be registered'
        );
    }

    public function test_it_adds_update_to_transient_when_available(): void
    {
        // Arrange
        $assetResolver = new AssetResolver();
        $logger = new UpdateLogger();
        $updateService = new GitHubUpdateService($this->config, $assetResolver, $logger);
        $integration = new PluginUpdateIntegration($updateService, $this->pluginBasename);

        // Mock update available (format matches GitHubUpdateService::cacheRelease)
        $mockReleaseData = [
            'version' => '1.2.3',
            'releaseUrl' => 'https://github.com/test-owner/test-repo/releases/tag/1.2.3',
            'releaseNotes' => 'Release notes',
            'publishedAt' => '2025-10-15T10:00:00+00:00',
            'assets' => [
                [
                    'name' => 'dwt-localfonts-1.2.3.zip',
                    'browser_download_url' => 'https://github.com/test-owner/test-repo/releases/download/1.2.3/dwt-localfonts-1.2.3.zip',
                    'size' => 1048576,
                ],
            ],
            'zipAssetUrl' => 'https://github.com/test-owner/test-repo/releases/download/1.2.3/dwt-localfonts-1.2.3.zip',
            'zipAssetSize' => 1048576,
        ];

        set_transient('dwt_localfonts_github_release', $mockReleaseData, 43200);

        // Create transient object
        $transient = (object) [
            'response' => [],
            'no_update' => [],
            'checked' => [
                $this->pluginBasename => '1.0.0',
            ],
        ];

        // Act
        $result = $integration->filterUpdateTransient($transient);

        // Assert
        $this->assertIsObject($result);
        $this->assertObjectHasProperty('response', $result);
        $this->assertArrayHasKey($this->pluginBasename, $result->response);

        $updateInfo = $result->response[$this->pluginBasename];
        $this->assertSame('dwt-localfonts', $updateInfo->slug);
        $this->assertSame('1.2.3', $updateInfo->new_version);
        $this->assertSame($this->pluginBasename, $updateInfo->plugin);
        $this->assertStringContainsString('dwt-localfonts-1.2.3.zip', $updateInfo->package);
    }

    public function test_it_does_not_modify_transient_when_no_update_available(): void
    {
        // Arrange
        $assetResolver = new AssetResolver();
        $logger = new UpdateLogger();
        $updateService = new GitHubUpdateService($this->config, $assetResolver, $logger);
        $integration = new PluginUpdateIntegration($updateService, $this->pluginBasename);

        // Set cache to null (no update available)
        set_transient('dwt_localfonts_github_release', null, 43200);

        // Create transient object
        $transient = (object) [
            'response' => [],
            'no_update' => [],
            'checked' => [
                $this->pluginBasename => '1.0.0',
            ],
        ];

        // Act
        $result = $integration->filterUpdateTransient($transient);

        // Assert - Response should be empty
        $this->assertIsObject($result);
        $this->assertEmpty($result->response);
    }

    public function test_it_handles_invalid_transient_gracefully(): void
    {
        // Arrange
        $assetResolver = new AssetResolver();
        $logger = new UpdateLogger();
        $updateService = new GitHubUpdateService($this->config, $assetResolver, $logger);
        $integration = new PluginUpdateIntegration($updateService, $this->pluginBasename);

        // Act
        $result = $integration->filterUpdateTransient(null);

        // Assert - Should return null unchanged
        $this->assertNull($result);
    }

    public function test_it_preserves_existing_plugin_updates(): void
    {
        // Arrange
        $assetResolver = new AssetResolver();
        $logger = new UpdateLogger();
        $updateService = new GitHubUpdateService($this->config, $assetResolver, $logger);
        $integration = new PluginUpdateIntegration($updateService, $this->pluginBasename);

        // Mock update available (format matches GitHubUpdateService::cacheRelease)
        $mockReleaseData = [
            'version' => '1.2.3',
            'releaseUrl' => 'https://github.com/test-owner/test-repo/releases/tag/1.2.3',
            'releaseNotes' => 'Release notes',
            'publishedAt' => '2025-10-15T10:00:00+00:00',
            'assets' => [
                [
                    'name' => 'dwt-localfonts-1.2.3.zip',
                    'browser_download_url' => 'https://github.com/test-owner/test-repo/releases/download/1.2.3/dwt-localfonts-1.2.3.zip',
                    'size' => 1048576,
                ],
            ],
            'zipAssetUrl' => 'https://github.com/test-owner/test-repo/releases/download/1.2.3/dwt-localfonts-1.2.3.zip',
            'zipAssetSize' => 1048576,
        ];

        set_transient('dwt_localfonts_github_release', $mockReleaseData, 43200);

        // Create transient with existing updates
        $transient = (object) [
            'response' => [
                'other-plugin/other-plugin.php' => (object) [
                    'slug' => 'other-plugin',
                    'new_version' => '2.0.0',
                    'package' => 'https://example.com/other-plugin-2.0.0.zip',
                ],
            ],
            'no_update' => [],
            'checked' => [
                $this->pluginBasename => '1.0.0',
                'other-plugin/other-plugin.php' => '1.0.0',
            ],
        ];

        // Act
        $result = $integration->filterUpdateTransient($transient);

        // Assert - Should preserve existing update
        $this->assertArrayHasKey('other-plugin/other-plugin.php', $result->response);
        $this->assertArrayHasKey($this->pluginBasename, $result->response);
        $this->assertCount(2, $result->response);
    }

    public function test_it_provides_plugin_info_for_modal(): void
    {
        // Arrange
        $assetResolver = new AssetResolver();
        $logger = new UpdateLogger();
        $updateService = new GitHubUpdateService($this->config, $assetResolver, $logger);
        $integration = new PluginUpdateIntegration($updateService, $this->pluginBasename);

        // Mock release data (format matches GitHubUpdateService::cacheRelease)
        $mockReleaseData = [
            'version' => '1.2.3',
            'releaseUrl' => 'https://github.com/test-owner/test-repo/releases/tag/1.2.3',
            'releaseNotes' => "## Changes\n- Feature A\n- Bug fix B",
            'publishedAt' => '2025-10-15T10:00:00+00:00',
            'assets' => [
                [
                    'name' => 'dwt-localfonts-1.2.3.zip',
                    'browser_download_url' => 'https://github.com/test-owner/test-repo/releases/download/1.2.3/dwt-localfonts-1.2.3.zip',
                    'size' => 1048576,
                ],
            ],
            'zipAssetUrl' => 'https://github.com/test-owner/test-repo/releases/download/1.2.3/dwt-localfonts-1.2.3.zip',
            'zipAssetSize' => 1048576,
        ];

        set_transient('dwt_localfonts_github_release', $mockReleaseData, 43200);

        // Create args object
        $args = (object) [
            'slug' => 'dwt-localfonts',
        ];

        // Act
        $result = $integration->filterPluginsApi(false, 'plugin_information', $args);

        // Assert
        $this->assertIsObject($result);
        $this->assertSame('dwt-localfonts', $result->slug);
        $this->assertSame('1.2.3', $result->version);
        $this->assertObjectHasProperty('sections', $result);
        $this->assertArrayHasKey('changelog', $result->sections);
        $this->assertStringContainsString('Feature A', $result->sections['changelog']);
    }

    public function test_it_returns_false_for_other_plugins_in_plugins_api(): void
    {
        // Arrange
        $assetResolver = new AssetResolver();
        $logger = new UpdateLogger();
        $updateService = new GitHubUpdateService($this->config, $assetResolver, $logger);
        $integration = new PluginUpdateIntegration($updateService, $this->pluginBasename);

        // Create args for different plugin
        $args = (object) [
            'slug' => 'other-plugin',
        ];

        // Act
        $result = $integration->filterPluginsApi(false, 'plugin_information', $args);

        // Assert - Should return false (not our plugin)
        $this->assertFalse($result);
    }

    public function test_it_handles_wp_error_from_update_service_gracefully(): void
    {
        // Arrange
        $assetResolver = new AssetResolver();
        $logger = new UpdateLogger();
        $updateService = new GitHubUpdateService($this->config, $assetResolver, $logger);
        $integration = new PluginUpdateIntegration($updateService, $this->pluginBasename);

        // Don't set any cache - will trigger API call to non-existent repo
        // This should result in WP_Error or null

        // Create transient object
        $transient = (object) [
            'response' => [],
            'no_update' => [],
            'checked' => [
                $this->pluginBasename => '1.0.0',
            ],
        ];

        // Act
        $result = $integration->filterUpdateTransient($transient);

        // Assert - Should return transient unchanged (error handled gracefully)
        $this->assertIsObject($result);
        // Response may be empty due to error
        $this->assertObjectHasProperty('response', $result);
    }

    public function test_full_update_check_workflow(): void
    {
        // Arrange - Simulate complete WordPress update check workflow
        $assetResolver = new AssetResolver();
        $logger = new UpdateLogger();
        $updateService = new GitHubUpdateService($this->config, $assetResolver, $logger);
        $integration = new PluginUpdateIntegration($updateService, $this->pluginBasename);

        // Register hooks
        $integration->registerHooks();

        // Mock release available
        $mockReleaseData = [
            'version' => '1.2.3',
            'releaseUrl' => 'https://github.com/test-owner/test-repo/releases/tag/1.2.3',
            'releaseNotes' => 'Release notes',
            'publishedAt' => '2025-10-15T10:00:00+00:00',
            'assets' => [
                [
                    'name' => 'dwt-localfonts-1.2.3.zip',
                    'browser_download_url' => 'https://github.com/test-owner/test-repo/releases/download/1.2.3/dwt-localfonts-1.2.3.zip',
                    'size' => 1048576,
                ],
            ],
        ];

        set_transient('dwt_localfonts_github_release', $mockReleaseData, 43200);

        // Act - Trigger WordPress update check
        $transient = (object) [
            'response' => [],
            'no_update' => [],
            'checked' => [
                $this->pluginBasename => '1.0.0',
            ],
        ];

        // Apply filter (simulates WordPress calling the filter)
        $result = apply_filters('pre_set_site_transient_update_plugins', $transient);

        // Assert - Update should be added to transient
        $this->assertIsObject($result);
        $this->assertArrayHasKey($this->pluginBasename, $result->response);
        $this->assertSame('1.2.3', $result->response[$this->pluginBasename]->new_version);
    }
}
