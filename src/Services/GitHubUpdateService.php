<?php

declare(strict_types=1);

namespace DWT\LocalFonts\Services;

use DWT\LocalFonts\ValueObjects\UpdateConfiguration;
use DWT\LocalFonts\ValueObjects\GitHubRelease;
use WP_Error;

/**
 * GitHub Update Service
 *
 * Handles GitHub API interaction for checking plugin updates.
 * Implements 12-hour transient caching and rate limit handling.
 *
 * @package DWT\LocalFonts\Services
 */
class GitHubUpdateService
{
    private const CACHE_KEY = 'dwt_localfonts_github_release';
    private const USER_AGENT = 'DWT-LocalFonts-Plugin';

    /**
     * Constructor
     *
     * @param UpdateConfiguration $config Update configuration
     * @param AssetResolver $assetResolver Asset resolver service
     * @param UpdateLogger|null $logger Update logger service (optional)
     */
    public function __construct(
        private readonly UpdateConfiguration $config,
        private readonly AssetResolver $assetResolver,
        private readonly ?UpdateLogger $logger = null
    ) {
    }

    /**
     * Check for plugin updates from GitHub
     *
     * Returns GitHubRelease if newer version available.
     * Returns null if current version is latest.
     * Returns WP_Error on API failure.
     *
     * @param bool $forceCheck Bypass cache and force fresh API call
     * @return GitHubRelease|null|WP_Error
     */
    public function checkForUpdates(bool $forceCheck = false): GitHubRelease|null|WP_Error
    {
        // Check cache unless force check requested
        if (!$forceCheck) {
            $cached = get_transient(self::CACHE_KEY);
            if ($cached !== false) {
                // Cached data exists - reconstruct GitHubRelease object
                return $this->reconstructReleaseFromCache($cached);
            }
        }

        // Fetch from GitHub API
        $apiUrl = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->config->repositoryOwner,
            $this->config->repositoryName
        );

        $response = wp_remote_get($apiUrl, [
            'headers' => [
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => self::USER_AGENT,
            ],
            'timeout' => 10,
        ]);

        // Handle network errors
        if (is_wp_error($response)) {
            $errorMessage = sprintf(
                'GitHub API request failed: %s',
                $response->get_error_message()
            );
            error_log($errorMessage);

            // Log error if logger available
            $this->logger?->logUpdateCheck($errorMessage, [
                'error_code' => $response->get_error_code(),
            ]);

            return $response;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Handle rate limiting (silent fallback)
        if ($statusCode === 403) {
            $responseData = json_decode($body, true);
            if (isset($responseData['message']) && strpos($responseData['message'], 'rate limit') !== false) {
                error_log('GitHub API rate limit exceeded. Using cached data or skipping update check.');

                // Log rate limit
                $retryAfter = wp_remote_retrieve_header($response, 'X-RateLimit-Reset') ?: 'unknown';
                $this->logger?->logRateLimit($retryAfter);

                return null; // Silent fallback - don't break admin
            }
        }

        // Handle other error status codes
        if ($statusCode !== 200) {
            error_log(sprintf(
                'GitHub API returned error: HTTP %d - %s',
                $statusCode,
                $body
            ));
            return new WP_Error(
                'github_api_error',
                sprintf('GitHub API returned HTTP %d', $statusCode),
                ['status_code' => $statusCode, 'response_body' => $body]
            );
        }

        // Parse API response
        $releaseData = json_decode($body, true);
        if ($releaseData === null) {
            return new WP_Error(
                'invalid_json',
                'Failed to parse GitHub API response',
                ['response_body' => $body]
            );
        }

        // Filter pre-releases if stable channel selected
        if ($this->config->updateChannel === 'stable' && ($releaseData['prerelease'] ?? false)) {
            // Cache null result to avoid repeated API calls
            set_transient(self::CACHE_KEY, null, $this->config->cacheLifetime);
            return null;
        }

        // Create GitHubRelease object
        $release = GitHubRelease::fromGitHubApiResponse($releaseData, $this->config->pluginSlug);
        if (is_wp_error($release)) {
            return $release;
        }

        // Check if this is a newer version
        if (!$this->isNewerVersion($release->version)) {
            // Cache null result
            set_transient(self::CACHE_KEY, null, $this->config->cacheLifetime);
            return null;
        }

        // Cache the release data
        $this->cacheRelease($release);

        // Log successful update check
        $this->logger?->logUpdateCheck('Update available', [
            'version' => $release->version,
            'release_url' => $release->releaseUrl,
        ]);

        return $release;
    }

    /**
     * Check if given version is newer than current plugin version
     *
     * @param string $remoteVersion Remote version string
     * @return bool True if remote version is newer
     */
    private function isNewerVersion(string $remoteVersion): bool
    {
        // Get current plugin version
        // In real implementation, this would be from plugin header
        // For now, we'll use a constant or method parameter
        $currentVersion = $this->getCurrentPluginVersion();

        return version_compare($remoteVersion, $currentVersion, '>');
    }

    /**
     * Get current plugin version
     *
     * @return string Current plugin version
     */
    private function getCurrentPluginVersion(): string
    {
        // In real implementation, this would read from plugin main file header
        // For testing, we return a version that can be overridden
        if (defined('DWT_LOCALFONTS_VERSION')) {
            return DWT_LOCALFONTS_VERSION;
        }

        // Fallback - read from plugin data
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $pluginFile = WP_PLUGIN_DIR . '/dwt-localfonts/dwt-localfonts.php';
        if (file_exists($pluginFile)) {
            $pluginData = get_plugin_data($pluginFile);
            return $pluginData['Version'] ?? '0.0.0';
        }

        return '0.0.0';
    }

    /**
     * Cache release data
     *
     * @param GitHubRelease $release Release to cache
     * @return void
     */
    private function cacheRelease(GitHubRelease $release): void
    {
        $cacheData = [
            'version' => $release->version,
            'releaseUrl' => $release->releaseUrl,
            'releaseNotes' => $release->releaseNotes,
            'publishedAt' => $release->publishedAt->format('c'),
            'assets' => $release->assets,
            'zipAssetUrl' => $release->zipAssetUrl,
            'zipAssetSize' => $release->zipAssetSize,
        ];

        set_transient(self::CACHE_KEY, $cacheData, $this->config->cacheLifetime);
    }

    /**
     * Reconstruct GitHubRelease from cached data
     *
     * @param mixed $cached Cached data (array or null)
     * @return GitHubRelease|null
     */
    private function reconstructReleaseFromCache(mixed $cached): GitHubRelease|null
    {
        if ($cached === null || !is_array($cached)) {
            return null;
        }

        try {
            return new GitHubRelease(
                version: $cached['version'],
                releaseUrl: $cached['releaseUrl'],
                releaseNotes: $cached['releaseNotes'],
                publishedAt: new \DateTimeImmutable($cached['publishedAt']),
                assets: $cached['assets'],
                zipAssetUrl: $cached['zipAssetUrl'] ?? null,
                zipAssetSize: $cached['zipAssetSize'] ?? 0
            );
        } catch (\Exception $e) {
            error_log(sprintf(
                'Failed to reconstruct GitHubRelease from cache: %s',
                $e->getMessage()
            ));
            return null;
        }
    }
}
