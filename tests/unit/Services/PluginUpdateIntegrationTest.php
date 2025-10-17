<?php

declare(strict_types=1);

namespace DWT\LocalFonts\Tests\Unit\Services;

use Brain\Monkey\Functions;
use DWT\LocalFonts\Services\PluginUpdateIntegration;
use DWT\LocalFonts\Services\GitHubUpdateService;
use DWT\LocalFonts\ValueObjects\GitHubRelease;
use PHPUnit\Framework\TestCase;
use Mockery;

/**
 * Test PluginUpdateIntegration service
 *
 * Tests WordPress filter integration and transient manipulation
 */
final class PluginUpdateIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Create a mock of the final GitHubUpdateService class
     *
     * @return GitHubUpdateService&\Mockery\MockInterface
     */
    private function createMockUpdateService(): GitHubUpdateService
    {
        return Mockery::mock(GitHubUpdateService::class);
    }

    public function test_it_registers_hooks(): void
    {
        // Arrange
        $updateService = $this->createMockUpdateService();
        $integration = new PluginUpdateIntegration($updateService, 'dwt-localfonts/dwt-localfonts.php');

        Functions\expect('add_filter')
            ->once()
            ->with('pre_set_site_transient_update_plugins', Mockery::type('callable'), 10, 1);

        Functions\expect('add_filter')
            ->once()
            ->with('plugins_api', Mockery::type('callable'), 10, 3);

        Functions\expect('add_filter')
            ->once()
            ->with('upgrader_pre_download', Mockery::type('callable'), 10, 3);

        // Act
        $integration->registerHooks();

        // Assert - verify method completed (Mockery verifies expectations automatically)
        $this->assertTrue(true);
    }

    public function test_it_adds_update_to_transient_when_available(): void
    {
        // Arrange
        $updateService = $this->createMockUpdateService();
        $integration = new PluginUpdateIntegration($updateService, 'dwt-localfonts/dwt-localfonts.php');

        $release = new GitHubRelease(
            version: '1.2.3',
            releaseUrl: 'https://github.com/owner/repo/releases/tag/1.2.3',
            releaseNotes: 'Release notes',
            publishedAt: new \DateTimeImmutable('2025-10-15T10:00:00Z'),
            assets: [
                [
                    'name' => 'dwt-localfonts-1.2.3.zip',
                    'browser_download_url' => 'https://github.com/owner/repo/releases/download/1.2.3/dwt-localfonts-1.2.3.zip',
                    'size' => 1048576,
                ],
            ],
            zipAssetUrl: 'https://github.com/owner/repo/releases/download/1.2.3/dwt-localfonts-1.2.3.zip',
            zipAssetSize: 1048576
        );

        $updateService->shouldReceive('checkForUpdates')
            ->once()
            ->andReturn($release);

        Functions\expect('is_wp_error')
            ->once()
            ->andReturn(false);

        $transient = (object) [
            'response' => [],
            'no_update' => [],
            'checked' => [
                'dwt-localfonts/dwt-localfonts.php' => '1.0.0',
            ],
        ];

        // Act
        $result = $integration->filterUpdateTransient($transient);

        // Assert
        $this->assertIsObject($result);
        $this->assertObjectHasProperty('response', $result);
        $this->assertArrayHasKey('dwt-localfonts/dwt-localfonts.php', $result->response);

        $updateInfo = $result->response['dwt-localfonts/dwt-localfonts.php'];
        $this->assertSame('dwt-localfonts', $updateInfo->slug);
        $this->assertSame('1.2.3', $updateInfo->new_version);
        $this->assertStringContainsString('dwt-localfonts-1.2.3.zip', $updateInfo->package);
    }

    public function test_it_does_not_modify_transient_when_no_update_available(): void
    {
        // Arrange
        $updateService = $this->createMockUpdateService();
        $integration = new PluginUpdateIntegration($updateService, 'dwt-localfonts/dwt-localfonts.php');

        $updateService->shouldReceive('checkForUpdates')
            ->once()
            ->andReturn(null); // No update available

        Functions\expect('is_wp_error')
            ->once()
            ->with(null)
            ->andReturn(false);

        $transient = (object) [
            'response' => [],
            'no_update' => [],
            'checked' => [
                'dwt-localfonts/dwt-localfonts.php' => '1.0.0',
            ],
        ];

        // Act
        $result = $integration->filterUpdateTransient($transient);

        // Assert
        $this->assertIsObject($result);
        $this->assertEmpty($result->response);
    }

    public function test_it_handles_invalid_transient_gracefully(): void
    {
        // Arrange
        $updateService = $this->createMockUpdateService();
        $integration = new PluginUpdateIntegration($updateService, 'dwt-localfonts/dwt-localfonts.php');

        $updateService->shouldNotReceive('checkForUpdates');

        // Act
        $result = $integration->filterUpdateTransient(null);

        // Assert
        $this->assertNull($result);
    }

    public function test_it_handles_wp_error_from_update_service(): void
    {
        // Arrange
        $updateService = $this->createMockUpdateService();
        $integration = new PluginUpdateIntegration($updateService, 'dwt-localfonts/dwt-localfonts.php');

        $wpError = new \WP_Error('api_error', 'GitHub API error');

        $updateService->shouldReceive('checkForUpdates')
            ->once()
            ->andReturn($wpError);

        Functions\expect('is_wp_error')
            ->once()
            ->with($wpError)
            ->andReturn(true);

        Functions\expect('error_log')
            ->once();

        $transient = (object) [
            'response' => [],
            'no_update' => [],
            'checked' => [
                'dwt-localfonts/dwt-localfonts.php' => '1.0.0',
            ],
        ];

        // Act
        $result = $integration->filterUpdateTransient($transient);

        // Assert - Should return transient unchanged
        $this->assertIsObject($result);
        $this->assertEmpty($result->response);
    }

    public function test_it_formats_plugin_info_correctly(): void
    {
        // Arrange
        $updateService = $this->createMockUpdateService();
        $integration = new PluginUpdateIntegration($updateService, 'dwt-localfonts/dwt-localfonts.php');

        $release = new GitHubRelease(
            version: '1.2.3',
            releaseUrl: 'https://github.com/owner/repo/releases/tag/1.2.3',
            releaseNotes: 'Release notes',
            publishedAt: new \DateTimeImmutable('2025-10-15T10:00:00Z'),
            assets: [
                [
                    'name' => 'dwt-localfonts-1.2.3.zip',
                    'browser_download_url' => 'https://github.com/owner/repo/releases/download/1.2.3/dwt-localfonts-1.2.3.zip',
                    'size' => 1048576,
                ],
            ],
            zipAssetUrl: 'https://github.com/owner/repo/releases/download/1.2.3/dwt-localfonts-1.2.3.zip',
            zipAssetSize: 1048576
        );

        $updateService->shouldReceive('checkForUpdates')
            ->once()
            ->andReturn($release);

        Functions\expect('is_wp_error')
            ->andReturn(false);

        $transient = (object) [
            'response' => [],
            'no_update' => [],
            'checked' => [
                'dwt-localfonts/dwt-localfonts.php' => '1.0.0',
            ],
        ];

        // Act
        $result = $integration->filterUpdateTransient($transient);

        // Assert
        $updateInfo = $result->response['dwt-localfonts/dwt-localfonts.php'];
        $this->assertSame('dwt-localfonts', $updateInfo->slug);
        $this->assertSame('dwt-localfonts/dwt-localfonts.php', $updateInfo->plugin);
        $this->assertSame('1.2.3', $updateInfo->new_version);
        $this->assertSame('https://github.com/owner/repo/releases/tag/1.2.3', $updateInfo->url);
    }

    public function test_it_preserves_existing_transient_responses(): void
    {
        // Arrange
        $updateService = $this->createMockUpdateService();
        $integration = new PluginUpdateIntegration($updateService, 'dwt-localfonts/dwt-localfonts.php');

        $release = new GitHubRelease(
            version: '1.2.3',
            releaseUrl: 'https://github.com/owner/repo/releases/tag/1.2.3',
            releaseNotes: 'Release notes',
            publishedAt: new \DateTimeImmutable('2025-10-15T10:00:00Z'),
            assets: [
                [
                    'name' => 'dwt-localfonts-1.2.3.zip',
                    'browser_download_url' => 'https://github.com/owner/repo/releases/download/1.2.3/dwt-localfonts-1.2.3.zip',
                    'size' => 1048576,
                ],
            ],
            zipAssetUrl: 'https://github.com/owner/repo/releases/download/1.2.3/dwt-localfonts-1.2.3.zip',
            zipAssetSize: 1048576
        );

        $updateService->shouldReceive('checkForUpdates')
            ->once()
            ->andReturn($release);

        Functions\expect('is_wp_error')
            ->andReturn(false);

        $transient = (object) [
            'response' => [
                'other-plugin/other-plugin.php' => (object) [
                    'slug' => 'other-plugin',
                    'new_version' => '2.0.0',
                ],
            ],
            'no_update' => [],
            'checked' => [
                'dwt-localfonts/dwt-localfonts.php' => '1.0.0',
                'other-plugin/other-plugin.php' => '1.0.0',
            ],
        ];

        // Act
        $result = $integration->filterUpdateTransient($transient);

        // Assert - Should preserve existing response
        $this->assertArrayHasKey('other-plugin/other-plugin.php', $result->response);
        $this->assertArrayHasKey('dwt-localfonts/dwt-localfonts.php', $result->response);
    }

    public function test_it_returns_false_for_plugins_api_when_not_our_plugin(): void
    {
        // Arrange
        $updateService = $this->createMockUpdateService();
        $integration = new PluginUpdateIntegration($updateService, 'dwt-localfonts/dwt-localfonts.php');

        $updateService->shouldNotReceive('checkForUpdates');

        // Act
        $result = $integration->filterPluginsApi(false, 'plugin_information', (object) ['slug' => 'other-plugin']);

        // Assert
        $this->assertFalse($result);
    }

    public function test_it_returns_plugin_info_for_our_plugin(): void
    {
        // Arrange
        $updateService = $this->createMockUpdateService();
        $integration = new PluginUpdateIntegration($updateService, 'dwt-localfonts/dwt-localfonts.php');

        $release = new GitHubRelease(
            version: '1.2.3',
            releaseUrl: 'https://github.com/owner/repo/releases/tag/1.2.3',
            releaseNotes: '## Changes\n- Feature A\n- Bug fix B',
            publishedAt: new \DateTimeImmutable('2025-10-15T10:00:00Z'),
            assets: [
                [
                    'name' => 'dwt-localfonts-1.2.3.zip',
                    'browser_download_url' => 'https://github.com/owner/repo/releases/download/1.2.3/dwt-localfonts-1.2.3.zip',
                    'size' => 1048576,
                ],
            ],
            zipAssetUrl: 'https://github.com/owner/repo/releases/download/1.2.3/dwt-localfonts-1.2.3.zip',
            zipAssetSize: 1048576
        );

        $updateService->shouldReceive('checkForUpdates')
            ->once()
            ->andReturn($release);

        Functions\expect('is_wp_error')
            ->andReturn(false);

        Functions\expect('wpautop')
            ->once()
            ->andReturnUsing(function ($text) {
                return '<p>' . $text . '</p>'; // Simple paragraph wrap for testing
            });

        Functions\expect('wp_kses_post')
            ->once()
            ->andReturnUsing(function ($text) {
                return $text; // Simple passthrough for testing
            });

        // Act
        $result = $integration->filterPluginsApi(false, 'plugin_information', (object) ['slug' => 'dwt-localfonts']);

        // Assert
        $this->assertIsObject($result);
        $this->assertSame('dwt-localfonts', $result->slug);
        $this->assertSame('1.2.3', $result->version);
        $this->assertObjectHasProperty('sections', $result);
        $this->assertArrayHasKey('changelog', $result->sections);
    }

    public function test_it_enables_auto_update_when_configured(): void
    {
        // Arrange
        $updateService = $this->createMockUpdateService();
        $integration = new PluginUpdateIntegration($updateService, 'dwt-localfonts/dwt-localfonts.php', null, true);

        Functions\expect('add_filter')
            ->with('auto_update_plugin', Mockery::type('callable'), 10, 2);

        Functions\expect('add_filter')
            ->with('pre_set_site_transient_update_plugins', Mockery::any(), Mockery::any(), Mockery::any());

        Functions\expect('add_filter')
            ->with('plugins_api', Mockery::any(), Mockery::any(), Mockery::any());

        Functions\expect('add_filter')
            ->with('upgrader_pre_download', Mockery::any(), Mockery::any(), Mockery::any());

        // Act
        $integration->registerHooks();

        // Assert - Mockery verifies auto_update_plugin filter registered
        $this->assertTrue(true);
    }

    public function test_it_does_not_enable_auto_update_when_disabled(): void
    {
        // Arrange
        $updateService = $this->createMockUpdateService();
        $integration = new PluginUpdateIntegration($updateService, 'dwt-localfonts/dwt-localfonts.php', null, false);

        Functions\expect('add_filter')
            ->never()
            ->with('auto_update_plugin', Mockery::any(), Mockery::any(), Mockery::any());

        Functions\expect('add_filter')
            ->with('pre_set_site_transient_update_plugins', Mockery::any(), Mockery::any(), Mockery::any());

        Functions\expect('add_filter')
            ->with('plugins_api', Mockery::any(), Mockery::any(), Mockery::any());

        Functions\expect('add_filter')
            ->with('upgrader_pre_download', Mockery::any(), Mockery::any(), Mockery::any());

        // Act
        $integration->registerHooks();

        // Assert - Mockery verifies auto_update_plugin filter NOT registered
        $this->assertTrue(true);
    }

    public function test_it_filters_auto_update_for_our_plugin_only(): void
    {
        // Arrange
        $updateService = $this->createMockUpdateService();
        $integration = new PluginUpdateIntegration($updateService, 'dwt-localfonts/dwt-localfonts.php', null, true);

        // Act - test with our plugin
        $result = $integration->filterAutoUpdate(false, (object) ['plugin' => 'dwt-localfonts/dwt-localfonts.php']);

        // Assert - should enable auto-update for our plugin
        $this->assertTrue($result);
    }

    public function test_it_does_not_filter_auto_update_for_other_plugins(): void
    {
        // Arrange
        $updateService = $this->createMockUpdateService();
        $integration = new PluginUpdateIntegration($updateService, 'dwt-localfonts/dwt-localfonts.php', null, true);

        // Act - test with different plugin
        $result = $integration->filterAutoUpdate(false, (object) ['plugin' => 'other-plugin/other-plugin.php']);

        // Assert - should preserve original value for other plugins
        $this->assertFalse($result);

        // Act - test with original value true
        $result = $integration->filterAutoUpdate(true, (object) ['plugin' => 'other-plugin/other-plugin.php']);

        // Assert - should preserve original value
        $this->assertTrue($result);
    }

    public function test_it_handles_missing_plugin_property_in_auto_update_filter(): void
    {
        // Arrange
        $updateService = $this->createMockUpdateService();
        $integration = new PluginUpdateIntegration($updateService, 'dwt-localfonts/dwt-localfonts.php', null, true);

        // Act - test with missing plugin property
        $result = $integration->filterAutoUpdate(false, (object) []);

        // Assert - should preserve original value when plugin property missing
        $this->assertFalse($result);
    }
}
