<?php

declare(strict_types=1);

namespace DWT\LocalFonts\Tests\Unit\Services;

use Brain\Monkey\Functions;
use DWT\LocalFonts\Services\GitHubUpdateService;
use DWT\LocalFonts\Services\AssetResolver;
use DWT\LocalFonts\ValueObjects\UpdateConfiguration;
use DWT\LocalFonts\ValueObjects\GitHubRelease;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * Test GitHubUpdateService
 *
 * Tests GitHub API interaction, caching, and rate limit handling
 */
final class GitHubUpdateServiceTest extends TestCase
{
    private UpdateConfiguration $config;

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        // Define plugin version constant for tests
        if (!defined('DWT_LOCALFONTS_VERSION')) {
            define('DWT_LOCALFONTS_VERSION', '1.0.0');
        }

        $this->config = new UpdateConfiguration(
            repositoryOwner: 'test-owner',
            repositoryName: 'test-repo',
            pluginSlug: 'dwt-localfonts',
            cacheLifetime: 43200,
            updateChannel: 'stable',
            autoUpdateEnabled: false
        );

        // Mock error_log to suppress output during tests
        Functions\expect('error_log')
            ->zeroOrMoreTimes()
            ->andReturnNull();
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function test_it_returns_cached_release_when_available(): void
    {
        // Arrange
        $service = new GitHubUpdateService($this->config, new AssetResolver());
        $cachedData = [
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

        Functions\expect('get_transient')
            ->once()
            ->with('dwt_localfonts_github_release')
            ->andReturn($cachedData);

        Functions\expect('is_wp_error')
            ->never();

        // Act
        $result = $service->checkForUpdates();

        // Assert
        $this->assertInstanceOf(GitHubRelease::class, $result);
        $this->assertSame('1.2.3', $result->version);
    }

    public function test_it_fetches_from_github_when_cache_empty(): void
    {
        // Arrange
        $service = new GitHubUpdateService($this->config, new AssetResolver());
        $apiResponse = [
            'tag_name' => '1.2.3',
            'html_url' => 'https://github.com/test-owner/test-repo/releases/tag/1.2.3',
            'body' => 'Release notes',
            'published_at' => '2025-10-15T10:00:00Z',
            'prerelease' => false,
            'assets' => [
                [
                    'name' => 'dwt-localfonts-1.2.3.zip',
                    'browser_download_url' => 'https://github.com/test-owner/test-repo/releases/download/1.2.3/dwt-localfonts-1.2.3.zip',
                    'size' => 1048576,
                ],
            ],
        ];

        Functions\expect('get_transient')
            ->once()
            ->andReturn(false);

        Functions\expect('wp_remote_get')
            ->once()
            ->with('https://api.github.com/repos/test-owner/test-repo/releases/latest', \Mockery::type('array'))
            ->andReturn([
                'response' => ['code' => 200],
                'body' => json_encode($apiResponse),
            ]);

        Functions\expect('wp_remote_retrieve_response_code')
            ->once()
            ->andReturn(200);

        Functions\expect('wp_remote_retrieve_body')
            ->once()
            ->andReturn(json_encode($apiResponse));

        Functions\expect('set_transient')
            ->once()
            ->with('dwt_localfonts_github_release', \Mockery::type('array'), 43200)
            ->andReturn(true);

        Functions\expect('is_wp_error')
            ->andReturn(false);

        // Act
        $result = $service->checkForUpdates();

        // Assert
        $this->assertInstanceOf(GitHubRelease::class, $result);
        $this->assertSame('1.2.3', $result->version);
    }

    public function test_it_bypasses_cache_when_force_check_enabled(): void
    {
        // Arrange
        $service = new GitHubUpdateService($this->config, new AssetResolver());
        $apiResponse = [
            'tag_name' => '1.2.3',
            'html_url' => 'https://github.com/test-owner/test-repo/releases/tag/1.2.3',
            'body' => 'Release notes',
            'published_at' => '2025-10-15T10:00:00Z',
            'prerelease' => false,
            'assets' => [
                [
                    'name' => 'dwt-localfonts-1.2.3.zip',
                    'browser_download_url' => 'https://github.com/test-owner/test-repo/releases/download/1.2.3/dwt-localfonts-1.2.3.zip',
                    'size' => 1048576,
                ],
            ],
        ];

        Functions\expect('get_transient')
            ->never(); // Cache should be bypassed

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn([
                'response' => ['code' => 200],
                'body' => json_encode($apiResponse),
            ]);

        Functions\expect('wp_remote_retrieve_response_code')
            ->once()
            ->andReturn(200);

        Functions\expect('wp_remote_retrieve_body')
            ->once()
            ->andReturn(json_encode($apiResponse));

        Functions\expect('set_transient')
            ->once()
            ->andReturn(true);

        Functions\expect('is_wp_error')
            ->andReturn(false);

        // Act
        $result = $service->checkForUpdates(forceCheck: true);

        // Assert
        $this->assertInstanceOf(GitHubRelease::class, $result);
    }

    public function test_it_handles_rate_limit_with_silent_fallback(): void
    {
        // Arrange
        $service = new GitHubUpdateService($this->config, new AssetResolver());

        Functions\expect('get_transient')
            ->once()
            ->andReturn(false);

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn([
                'response' => ['code' => 403],
                'body' => json_encode(['message' => 'API rate limit exceeded']),
            ]);

        Functions\expect('wp_remote_retrieve_response_code')
            ->once()
            ->andReturn(403);

        Functions\expect('wp_remote_retrieve_body')
            ->once()
            ->andReturn(json_encode(['message' => 'API rate limit exceeded']));

        Functions\expect('wp_remote_retrieve_header')
            ->once()
            ->with(\Mockery::any(), 'X-RateLimit-Reset')
            ->andReturn('1234567890');

        Functions\expect('is_wp_error')
            ->andReturn(false);

        // Act
        $result = $service->checkForUpdates();

        // Assert - Should return null on rate limit (silent fallback)
        $this->assertNull($result);
    }

    public function test_it_returns_wp_error_on_network_failure(): void
    {
        // Arrange
        $service = new GitHubUpdateService($this->config, new AssetResolver());
        $wpError = new WP_Error('http_request_failed', 'Network error');

        Functions\expect('get_transient')
            ->once()
            ->andReturn(false);

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn($wpError);

        Functions\expect('is_wp_error')
            ->once()
            ->with($wpError)
            ->andReturn(true);

        // Act
        $result = $service->checkForUpdates();

        // Assert
        $this->assertInstanceOf(WP_Error::class, $result);
    }

    public function test_it_returns_null_when_no_update_available(): void
    {
        // Arrange
        $config = new UpdateConfiguration(
            repositoryOwner: 'test-owner',
            repositoryName: 'test-repo',
            pluginSlug: 'dwt-localfonts',
            cacheLifetime: 43200,
            updateChannel: 'stable',
            autoUpdateEnabled: false
        );

        $service = new GitHubUpdateService($config, new AssetResolver());
        $apiResponse = [
            'tag_name' => '1.0.0', // Same as current version
            'html_url' => 'https://github.com/test-owner/test-repo/releases/tag/1.0.0',
            'body' => 'Current release',
            'published_at' => '2025-10-15T10:00:00Z',
            'prerelease' => false,
            'assets' => [
                [
                    'name' => 'dwt-localfonts-1.0.0.zip',
                    'browser_download_url' => 'https://github.com/test-owner/test-repo/releases/download/1.0.0/dwt-localfonts-1.0.0.zip',
                    'size' => 1048576,
                ],
            ],
        ];

        Functions\expect('get_transient')
            ->once()
            ->andReturn(false);

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn([
                'response' => ['code' => 200],
                'body' => json_encode($apiResponse),
            ]);

        Functions\expect('wp_remote_retrieve_response_code')
            ->once()
            ->andReturn(200);

        Functions\expect('wp_remote_retrieve_body')
            ->once()
            ->andReturn(json_encode($apiResponse));

        Functions\expect('is_wp_error')
            ->andReturn(false);

        // Mock version_compare to return false (no newer version)
        Functions\expect('version_compare')
            ->once()
            ->andReturn(false);

        // Mock set_transient to cache null result
        Functions\expect('set_transient')
            ->once()
            ->with('dwt_localfonts_github_release', null, 43200)
            ->andReturn(true);

        // Act
        $result = $service->checkForUpdates();

        // Assert
        $this->assertNull($result);
    }

    public function test_it_filters_prerelease_when_stable_channel_selected(): void
    {
        // Arrange
        $service = new GitHubUpdateService($this->config, new AssetResolver());
        $apiResponse = [
            'tag_name' => '2.0.0-beta',
            'html_url' => 'https://github.com/test-owner/test-repo/releases/tag/2.0.0-beta',
            'body' => 'Beta release',
            'published_at' => '2025-10-15T10:00:00Z',
            'prerelease' => true, // Pre-release flag
            'assets' => [
                [
                    'name' => 'dwt-localfonts-2.0.0-beta.zip',
                    'browser_download_url' => 'https://github.com/test-owner/test-repo/releases/download/2.0.0-beta/dwt-localfonts-2.0.0-beta.zip',
                    'size' => 1048576,
                ],
            ],
        ];

        Functions\expect('get_transient')
            ->once()
            ->andReturn(false);

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn([
                'response' => ['code' => 200],
                'body' => json_encode($apiResponse),
            ]);

        Functions\expect('wp_remote_retrieve_response_code')
            ->once()
            ->andReturn(200);

        Functions\expect('wp_remote_retrieve_body')
            ->once()
            ->andReturn(json_encode($apiResponse));

        Functions\expect('is_wp_error')
            ->andReturn(false);

        Functions\expect('set_transient')
            ->once()
            ->andReturn(true);

        // Act
        $result = $service->checkForUpdates();

        // Assert - Should return null (pre-release filtered out)
        $this->assertNull($result);
    }

    public function test_it_includes_prerelease_when_all_channel_selected(): void
    {
        // Arrange
        $config = new UpdateConfiguration(
            repositoryOwner: 'test-owner',
            repositoryName: 'test-repo',
            pluginSlug: 'dwt-localfonts',
            cacheLifetime: 43200,
            updateChannel: 'all', // All releases channel
            autoUpdateEnabled: false
        );

        $service = new GitHubUpdateService($config, new AssetResolver());
        $apiResponse = [
            'tag_name' => '2.0.0-beta',
            'html_url' => 'https://github.com/test-owner/test-repo/releases/tag/2.0.0-beta',
            'body' => 'Beta release',
            'published_at' => '2025-10-15T10:00:00Z',
            'prerelease' => true,
            'assets' => [
                [
                    'name' => 'dwt-localfonts-2.0.0-beta.zip',
                    'browser_download_url' => 'https://github.com/test-owner/test-repo/releases/download/2.0.0-beta/dwt-localfonts-2.0.0-beta.zip',
                    'size' => 1048576,
                ],
            ],
        ];

        Functions\expect('get_transient')
            ->once()
            ->andReturn(false);

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn([
                'response' => ['code' => 200],
                'body' => json_encode($apiResponse),
            ]);

        Functions\expect('wp_remote_retrieve_response_code')
            ->once()
            ->andReturn(200);

        Functions\expect('wp_remote_retrieve_body')
            ->once()
            ->andReturn(json_encode($apiResponse));

        Functions\expect('set_transient')
            ->once()
            ->andReturn(true);

        Functions\expect('is_wp_error')
            ->andReturn(false);

        // Mock version_compare to return true (newer version)
        Functions\expect('version_compare')
            ->once()
            ->andReturn(true);

        // Act
        $result = $service->checkForUpdates();

        // Assert - Should return release (pre-release included)
        $this->assertInstanceOf(GitHubRelease::class, $result);
    }
}
